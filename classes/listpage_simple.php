<?php
/**
 * Class for list page with mode simple
 */
class ListPage_Simple extends ListPage 
{
	/**
	 * Constructor, set initial params
	 *
	 * @param array $params
	 */	
	function __construct(&$params) 
	{
		// call parent constructor
		parent::__construct($params);	
		$this->pSetEdit = new ProjectSettings($this->tName, PAGE_SEARCH);
	}
	
	/**
	 * Add common assign for simple mode on list page
	 */	
	function commonAssign() 
	{
		parent::commonAssign();

		$this->importLinksAttrs();

		$this->xt->assign("left_block", true);
		
		$this->addAssignTopLinks();
		
		$this->addAssignPageDetails();	
		
		$this->xt->assign("moreButtons", $this->exportAvailable() || $this->printAvailable() || $this->importAvailable() || $this->advSearchAvailable() );		
		$this->xt->assign("withSelected", $this->exportAvailable() || $this->printAvailable() || $this->inlineEditAvailable() || $this->deleteAvailable() );
		
		if( $this->exportAvailable() )
		{
			$this->xt->assign("exportselected_link", true);
			$this->xt->assign("exportselectedlink_span", $this->buttonShowHideStyle());
			$this->xt->assign("exportselectedlink_attrs", $this->getPrintExportLinkAttrs('export'));
		}

	
		if( $this->printAvailable() )
		{
			// new print panel
			if ( !$this->recordsOnPage )
			{
				$this->hideItemType("print_panel");
			}
			
			$this->xt->assign("print_friendly", true);
			$this->xt->assign("print_friendly_all", true);
			$this->xt->assign("print_recspp", $this->pSet->getPrinterSplitRecords() );

			foreach( $this->pSet->getDetailsTables() as $details ) {
				$dPset = new ProjectSettings( $details );
				if( $dPset->hasPrintPage() && $this->permis[ $details ]["export"] )
				{
					$this->xt->assign("print_details", true);
					$this->xt->assign("print_details_" . GetTableURL( $details ), true);
				}
			}
					
			// end new print panel
			$this->xt->assign("printselected_link", true);
			$this->xt->assign("printselectedlink_attrs", $this->getPrintExportLinkAttrs('print'));
			$this->xt->assign("printselectedlink_span", $this->buttonShowHideStyle());
		}
		
		//advanced search and attr
		$this->xt->assign("advsearchlink_attrs", "id=\"advButton".$this->id."\"");
				
		$this->xt->assign('menu_block', $this->isShowMenu() || $this->isAdminTable());
		
		
	}

	
	function getBreadcrumbMenuId() {
		if( $this->isAdminTable() )
			return "adminarea";
		else
			return "main";
	}
	

	
	/**
	 * Add assign for top links, blocks and more button links
	 *
	 * @param boolean $exportPermis
	 */
	function addAssignTopLinks()
	{
		if( !$this->isDispGrid() && !$this->pSetEdit->ajaxBasedListPage() )
			return;
		
		if( $this->printAvailable() )
		{
			// print links and attrs
			$this->xt->assign("prints_block", true );
			$this->xt->assign("print_link", $this->recordsOnPage );
			$this->xt->assign("printlink_attrs", "id='print_".$this->id."' name='print_".$this->id."'".(!$this->recordsOnPage && $needShowLinkForAdd ? " style='display:none;'" : ""));
			//print all link and attr
			$this->xt->assign("printall_link", true );
			$this->xt->assign("printalllink_attrs","id='printAll_".$this->id."' name='printAll_".$this->id."'". (!$this->recordsOnPage ? " style='display:none;'" : ""));
		}
		
		if( $this->exportAvailable() )
		{
			//export link and attr
			$this->xt->assign("export_link", true );
			$this->xt->assign("exportlink_attrs", "id='export_".$this->id."'".(!$this->recordsOnPage ? " style='display:none;'" : ""));
							 
		}
	}
	
	/**
	 * Add common html code for simple mode on list page
	 */	
	function addCommonHtml() 
	{
		$this->body ["begin"] .= GetBaseScriptsForPage($this->isDisplayLoading); 
		
		//add parent common html code
		parent::addCommonHtml();
		
		// assign body end
		$this->body['end'] = XTempl::create_method_assignment( "assignBodyEnd", $this);
	}
	
	function buildSearchPanel() {
		if( !$this->permis[ $this->tName ]["search"] ) {
			return;
		}
		
		$this->xt->enable_section("searchPanel");
		
		$params = array();
		$params['pageObj'] = &$this;
		$params['panelSearchFields'] = $this->panelSearchFields;
		$masterKeys = $this->pSet->getMasterKeys( $this->masterTable );
		$params['allSearchFields'] = array_diff( 
			$this->pSet->getAllSearchFields(), 
			$masterKeys['detailsKeys'] 
		);
		
		$this->searchPanel = new SearchPanelSimple( $params );
		$this->searchPanel->buildSearchPanel();
	}

	/**
	 * If use resizable columns
	 * Prepare for resize main table
	 */
	function prepareForResizeColumns()
	{
		parent::prepareForResizeColumns();
		include_once getabspath("classes/paramsLogger.php");	
		$logger = new paramsLogger( $this->tName, CRESIZE_PARAMS_TYPE );
		$columnsData = $logger->getData();
		if( $columnsData )
			$this->pageData[ "resizableColumnsData" ] = $columnsData;
	}
	
	protected function getColumnsToHide()  
	{
		return $this->getCombinedHiddenColumns();
	}

	/**
	 * Add data-brick=filterpanel container
	 */
	protected function prepareEmptyFPMarkup()
	{
		if( $this->listAjax && $this->pSetEdit->isSearchRequiredForFiltering() && !$this->isRequiredSearchRunning()  )
		{
			$this->xt->enable_section("filterPanel");
			$this->hideElement("filterpanel");
		}		
	}

	function isAllowShowHideFields() {
		return $this->pSet->isAllowShowHideFields();
	}

	protected function reorderFieldsFeatureEnabled() {
		return $this->pSet->isAllowFieldsReordering();
	}

}
?>