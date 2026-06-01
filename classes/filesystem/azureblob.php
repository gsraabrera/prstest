<?php

define('AZURE_BLOB_VERSION', '2018-11-09');
define('AZURE_EXPIRATION_TIME_SECONDS', 30);

class AzureBlobFileSystem extends RunnerFileSystem {

    public $accountName;
    public $accountKey;
    public $container;
    public $path;

    protected $_fileInfoCache = array();

    public function __construct($params) {
        $this->accountName = $params['accountName'];
        $this->accountKey = $params['accountKey'];
        $this->container = $params['container'];
        $this->path = $this->prepareFilePath($params["path"]);
    }

    protected function prepareFilePath($path) {
        return AzureBlobFileSystem::normalizePath($path);
    }

    protected function endpoint() {
        return "https://" . $this->accountName . ".blob.core.windows.net";
    }

    protected function objectUrl($resourceKey) {
        return $this->endpoint() . "/" . $this->container . "/" . $resourceKey;
    }

    protected function sign($stringToSign) {
        return base64_encode_binary(
            hash_hmac_sha256(
                $stringToSign,
                base64_decode_binary($this->accountKey),
                true
            )
        );
    }

    protected function canonicalHeaders($headers) {
        $xms = array();

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);

            if (strpos($lowerKey, 'x-ms-') === 0) {
                $xms[$lowerKey] = trim($value);
            }
        }

        ksort($xms);

        $result = "";
        foreach ($xms as $key => $value) {
            $result .= $key . ":" . $value . "\n";
        }

        return $result;
    }

    protected function canonicalResource($resourceKey) {
        return "/" . $this->accountName .
            "/" . $this->container .
            "/" . $resourceKey;
    }

    /**
     * https://learn.microsoft.com/en-us/rest/api/storageservices/authorize-with-shared-key
     */
    protected function buildStringToSign($method, $resourceKey, $headers) {

        $contentLength = "";
        if (
            isset($headers["Content-Length"])
            && $headers["Content-Length"] != 0
        ) {
            $contentLength = $headers["Content-Length"];
        }

        return
            $method . "\n" .                 // VERB
            "\n" .                          // Content-Encoding
            "\n" .                          // Content-Language
            $contentLength . "\n" .         // Content-Length
            "\n" .                          // Content-MD5
            (
                isset($headers["Content-Type"])
                    ? $headers["Content-Type"]
                    : ""
            ) . "\n" .                      // Content-Type
            "\n" .                          // Date
            "\n" .                          // If-Modified-Since
            "\n" .                          // If-Match
            "\n" .                          // If-None-Match
            "\n" .                          // If-Unmodified-Since
            "\n" .                          // Range
            $this->canonicalHeaders($headers) .
            $this->canonicalResource($resourceKey);
    }

    protected function authorizeRequest(&$request, $method, $resourceKey) {

        $stringToSign = $this->buildStringToSign(
            $method,
            $resourceKey,
            $request->headers
        );

        $signature = $this->sign($stringToSign);

        $request->headers["Authorization"] =
            "SharedKey " .
            $this->accountName .
            ":" .
            $signature;
    }

    protected function addRequiredHeaders(&$headers) {
        $headers["x-ms-date"] = httpDateString();
//        $headers["x-ms-date"] = gmdate("D, d M Y H:i:s T");
        $headers["x-ms-version"] = AZURE_BLOB_VERSION;
    }

    /**
     * PUT blob
     */
    protected function saveFile($data, $resourceKey) {

        $request = new HttpRequest(
            $this->objectUrl($resourceKey),
            "PUT"
        );

        $request->body = $data;

        $this->addRequiredHeaders($request->headers);

        $request->headers["x-ms-blob-type"] = "BlockBlob";
        $request->headers["Content-Type"] = "application/octet-stream";
        $request->headers["Content-Length"] = strlen_bin($data);

        $this->authorizeRequest(
            $request,
            "PUT",
            $resourceKey
        );

        $response = $request->run();

        if ($response["responseCode"] == 201) {
            return $resourceKey;
        }

        $this->setLastError($response["content"]);

        return null;
    }

    public function saveUploadedFile($uploadFile, $userFilename) {

        $uniqueFilename = $this->tryCreateUniqueFile(
            $userFilename,
            $this->path
        );

        if (!$uniqueFilename) {
            $this->setLastError(
                "Unable to get unique file name for " . $userFilename
            );

            return null;
        }

        $data = RunnerFileSystem::uploadedFileContent($uploadFile);

        $resourceKey = $this->path . $uniqueFilename;

        return $this->saveFile($data, $resourceKey);
    }

    /**
     * DELETE blob
     */
    public function delete($resourceKey) {

        $request = new HttpRequest(
            $this->objectUrl($resourceKey),
            "DELETE"
        );

        $this->addRequiredHeaders($request->headers);

        $this->authorizeRequest(
            $request,
            "DELETE",
            $resourceKey
        );

        $request->run();
    }

    /**
     * SAS download URL
     */
    public function redirectToFile($resourceKey, $thumbnail) {

		$expiry = iso8601date( time() + AZURE_EXPIRATION_TIME_SECONDS );

        $canonicalResource =
            "/blob/" .
            $this->accountName .
            "/" .
            $this->container .
            "/" .
            $resourceKey;

        $stringToSign =
            "r\n" .                 // permissions
            "\n" .                  // start time
            $expiry . "\n" .        // expiry
            $canonicalResource . "\n" .
            "\n" .                  // identifier
            "\n" .                  // IP
            "https\n" .             // protocol
            AZURE_BLOB_VERSION . "\n" .
            "b\n" .                 // resource = blob
            "\n\n\n\n\n";

        $signature = urlencode(
            $this->sign($stringToSign)
        );

        $url =
            $this->objectUrl($resourceKey) .
            "?sv=" . urlencode(AZURE_BLOB_VERSION) .
            "&spr=https" .
            "&se=" . urlencode($expiry) .
            "&sr=b" .
            "&sp=r" .
            "&sig=" . $signature;

        header("Location:" . $url);
        exit();
    }

    /**
     * HEAD blob
     */
    public function getFileInfo($resourceKey) {

        if ($this->_fileInfoCache[$resourceKey]) {
            return $this->_fileInfoCache[$resourceKey];
        }

        $request = new HttpRequest(
            $this->objectUrl($resourceKey),
            "HEAD"
        );

        $this->addRequiredHeaders($request->headers);

        $this->authorizeRequest(
            $request,
            "HEAD",
            $resourceKey
        );

        $result = $request->run();

        if ($result["responseCode"] != 200) {
            return null;
        }

        $headers = HttpRequest::parseHeaders($result);

        $ret = array(
            "fullPath" => $resourceKey,
            "size" => $headers["content-length"],
            "raw" => $result,
            "returnContent" => false
        );

        $this->_fileInfoCache[$resourceKey] = $ret;

        return $ret;
    }

    /**
     * Direct upload using SAS URL
     */
    public function initUpload($userFilename) {

        $uniqueFilename = $this->tryCreateUniqueFile(
            $userFilename,
            $this->path
        );

        if (!$uniqueFilename) {
            return null;
        }

        $resourceKey = $this->path . $uniqueFilename;

		$expiry = iso8601date( time() + AZURE_EXPIRATION_TIME_SECONDS );
        $canonicalResource =
            "/blob/" .
            $this->accountName .
            "/" .
            $this->container .
            "/" .
            $resourceKey;

        $stringToSign =
            "w\n" .                 // permissions
            "\n" .                  // start time
            $expiry . "\n" .
            $canonicalResource . "\n" .
            "\n" .                  // identifier
            "\n" .                  // IP
            "https\n" .
            AZURE_BLOB_VERSION . "\n" .
            "b\n" .
            "\n\n\n\n\n";

        $signature = urlencode(
            $this->sign($stringToSign)
        );

        $url =
            $this->objectUrl($resourceKey) .
            "?sv=" . urlencode(AZURE_BLOB_VERSION) .
            "&spr=https" .
            "&se=" . urlencode($expiry) .
            "&sr=b" .
            "&sp=w" .
            "&sig=" . $signature;

        return array(
            "uploadParams" => array(
                "url" => $url
            ),
            "fileId" => $resourceKey
        );
    }

    public function directUpload() {
        return true;
    }

    /**
     * Same normalization as S3/GCS
     */
    public static function normalizePath($path) {

        if (!$path) {
            return '';
        }

        if (substr($path, -1) != '/') {
            $path .= '/';
        }

        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }

        return $path;
    }

    protected function tryCreateFile($path) {

        if (!$this->getFileInfo($path)) {
            $this->saveFile($this->stubFileData, $path);
            return true;
        }

        return false;
    }
}

?>