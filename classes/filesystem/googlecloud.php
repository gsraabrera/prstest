<?php

define('GCS_EXPIRATION_TIME_SECONDS', 30);

class GoogleCloudFileSystem extends RunnerFileSystem {

    public $accessKey;
    public $secretKey;
    public $bucket;
    public $path;

    protected $_fileInfoCache = array();

    public function __construct($params) {
        $this->accessKey = $params['accessKey'];
        $this->secretKey = $params['secretKey'];
        $this->bucket = $params['bucket'];
    }

    protected function prepareFilePath($path) {
        return GoogleCloudFileSystem::normalizePath($path);
    }

    protected function endpoint() {
        return 'https://storage.googleapis.com';
    }

    protected function objectUrl($resourceKey) {
        return $this->endpoint() . '/' . $this->bucket . '/' . $resourceKey;
    }

    /**
     * Build canonical string for signing (GCS XML API)
     */
    protected function buildStringToSign($method, $resource, $headers = array(), $expires = null) {
        $contentMd5 = isset($headers["Content-MD5"]) ? $headers["Content-MD5"] : "";
        $contentType = isset($headers["Content-Type"]) ? $headers["Content-Type"] : "";
        $date = $expires ? $expires : (isset($headers["Date"]) ? $headers["Date"] : "");

        $canonicalHeaders = "";
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            if (strpos($lk, "x-goog-") === 0) {
                $canonicalHeaders .= $lk . ":" . trim($v) . "\n";
            }
        }

        $canonicalResource = "/" . $this->bucket . "/" . $resource;

        return $method . "\n" .
            $contentMd5 . "\n" .
            $contentType . "\n" .
            $date . "\n" .
            $canonicalHeaders .
            $canonicalResource;
    }

    protected function sign($stringToSign) {
        return base64_encode_binary(hash_hmac_sha1( $stringToSign, $this->secretKey, true));
    }

    /**
     * Upload file (PUT)
     */
    protected function saveFile($data, $resourceKey) {
        $url = $this->objectUrl($resourceKey);

        $request = new HttpRequest($url, "PUT");
        $request->body = $data;

        $date = httpDateString();

        $request->headers["Date"] = $date;
        $request->headers["Content-Type"] = "application/octet-stream";
        $request->headers["Content-Length"] = strlen_bin($data);

        $stringToSign = $this->buildStringToSign("PUT", $resourceKey, $request->headers);
        $signature = $this->sign($stringToSign);

        $request->headers["Authorization"] = "AWS " . $this->accessKey . ":" . $signature;

        $response = $request->run();
        if ($response["responseCode"] == 200) {
            return $resourceKey;
        }

        $this->setLastError($response["content"]);
        return null;
    }

    public function saveUploadedFile($uploadFile, $userFilename) {
        $uniqueFilename = $this->tryCreateUniqueFile($userFilename, $this->path);
        if (!$uniqueFilename) {
            $this->setLastError("Unable to get unique file name for " . $userFilename);
            return null;
        }

        $data = RunnerFileSystem::uploadedFileContent($uploadFile);
        $resourceKey = $this->path . $uniqueFilename;

        return $this->saveFile($data, $resourceKey);
    }

    /**
     * DELETE object
     */
    public function delete($resourceKey) {
        $url = $this->objectUrl($resourceKey);

        $request = new HttpRequest($url, "DELETE");

        $date = httpDateString();
        $request->headers["Date"] = $date;

        $stringToSign = $this->buildStringToSign("DELETE", $resourceKey, $request->headers);
        $signature = $this->sign($stringToSign);

        $request->headers["Authorization"] = "AWS " . $this->accessKey . ":" . $signature;

        $request->run();
    }

    /**
     * Signed URL for download
     */
    public function redirectToFile($resourceKey, $thumbnail) {
        $expires = time() + GCS_EXPIRATION_TIME_SECONDS;

        $stringToSign = "GET\n\n\n" . $expires . "\n/" . $this->bucket . "/" . $resourceKey;
        $signature = urlencode($this->sign($stringToSign));

        $url = $this->endpoint() . "/" . $this->bucket . "/" . $resourceKey .
            "?GoogleAccessId=" . $this->accessKey .
            "&Expires=" . $expires .
            "&Signature=" . $signature;

        header("Location:" . $url);
        exit();
    }

    /**
     * HEAD request
     */
    public function getFileInfo($resourceKey) {
        if ($this->_fileInfoCache[$resourceKey]) {
            return $this->_fileInfoCache[$resourceKey];
        }

        $url = $this->objectUrl($resourceKey);
        $request = new HttpRequest($url, "HEAD");

        $date = httpDateString();
        $request->headers["Date"] = $date;

        $stringToSign = $this->buildStringToSign("HEAD", $resourceKey, $request->headers);
        $signature = $this->sign($stringToSign);

        $request->headers["Authorization"] = "AWS " . $this->accessKey . ":" . $signature;

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
     * Direct upload (POST policy)
     */
    public function initUpload($userFilename) {

        $uniqueFilename = $this->tryCreateUniqueFile($userFilename, $this->path);
        if (!$uniqueFilename) {
            return null;
        }

        $resourceKey = $this->path . $uniqueFilename;
        $expiration = iso8601date( time() + GCS_EXPIRATION_TIME_SECONDS );

        $policy = array(
            "expiration" => $expiration,
            "conditions" => array(
                array("bucket" => $this->bucket),
                array("key" => $resourceKey)
            )
        );

        $policyBase64 = base64_encode(runner_json_encode($policy));
        $signature = base64_encode_binary(hash_hmac_sha1( $policyBase64, $this->secretKey, true));

        $uploadParams = array(
            "key" => $resourceKey,
            "GoogleAccessId" => $this->accessKey,
            "policy" => $policyBase64,
            "signature" => $signature
        );

        return array(
            "uploadParams" => array(
                "url" => $this->endpoint() . "/" . $this->bucket,
                "data" => $uploadParams
            ),
            "fileId" => $resourceKey
        );
    }

    public function directUpload() {
        return true;
    }

    /**
     * Same normalization as S3
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