<?php
include('ControlConnection.php') ;
class selectorVendor extends ControlConnection{
	
	function selectAllTabsView(){
		$select = "";
	$menuTables = "SELECT * from page_menu_tab  WHERE tab_status = ? " ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($menuTables);
	
	$queryTabs->execute(array( $status ) );
	
	if($queryTabs->rowCount() > 0 ):
			$select = '<option value="">Select Option</option>';
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->ID.'" >'.$result->tab_name .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
	

		function selectTitleView(){
		$select = "";
	$selectstatus = "SELECT * from hr_salutation WHERE `titlestatus` = ?  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( 1) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->Salutation.'">'.$result->Salutation .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
function selectAllPagesView(){
		$select = "";
	$pages = "SELECT * from `page_main_menus` " ;
										
	$querpages = $this->entityManager->prepare();
	
	$querpages->execute();
	
	if($querpages->rowCount() > 0 ):
			
		while( $pageresult = $querpages->fetchObject()):
	 
			$select .= '<option value="'.$pageresult->pageID.'">'.$pageresult->menu_name.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
function selectAllSalaryItemTypeView(){
		$select = "";
	$itemtypes = "SELECT * from `hr_salary_item_type`  " ;
										
	$queritemtype = $this->entityManager->prepare($itemtypes);
	
	$queritemtype->execute();
	
	if($queritemtype->rowCount() > 0 ):
			
		while( $pageresult = $queritemtype->fetchObject()):
	 
			$select .= '<option value="'.$pageresult->itemTypeID.'">'.$pageresult->itemTypeName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	

function selectAllSalaryItemTypeAllowancesView(){
		$select = "";
	$itemtypes = "SELECT * from `hr_salary_item_type` WHERE typeUsedStatus = 'p' " ;
										
	$queritemtype = $this->entityManager->prepare($itemtypes);
	
	$queritemtype->execute();
	
	if($queritemtype->rowCount() > 0 ):
			
		while( $pageresult = $queritemtype->fetchObject()):
	 
			$select .= '<option value="'.$pageresult->itemTypeID.'">'.$pageresult->itemTypeName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	

function selectAllSalaryItemTypeDeductionView(){
		$select = "";
	$itemtypes = "SELECT * from `hr_salary_item_type` WHERE typeUsedStatus = 'd' " ;
										
	$queritemtype = $this->entityManager->prepare($itemtypes);
	
	$queritemtype->execute();
	
	if($queritemtype->rowCount() > 0 ):
			
		while( $pageresult = $queritemtype->fetchObject()):
	 
			$select .= '<option value="'.$pageresult->itemTypeID.'">'.$pageresult->itemTypeName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	
function selectAllItemCategoryView(){
		$select = "";
	$itemcategory = "SELECT * from `hr_item_category`  " ;
										
	$queritemcat = $this->entityManager->prepare($itemcategory);
	
	$queritemcat->execute();
	
	if($queritemcat->rowCount() > 0 ):
			
		while( $pageresult = $queritemcat->fetchObject()):
	 
			$select .= '<option value="'.$pageresult->itemcatID.'">'.$pageresult->itemcatName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}

		
	function selectRoleView(){
		$select = "";
		$rolesLoaded = false;
		try {
			$selectstatus = "SELECT * from acd_tbluser WHERE 1  " ;
			$getstatus = $this->entityManager->prepare($selectstatus);
			$getstatus->execute(array() );
			if($getstatus->rowCount() > 0 ):
				while( $result = $getstatus->fetchObject()):
					$select .= '<option value="'.$result->ID.'">'.$result->access .' </option>';
				endwhile;
				$rolesLoaded = true;
			endif;
		} catch (Throwable $e) {}

		if (!$rolesLoaded) {
			try {
				$selectstatus = "SELECT * from roles  " ;
				$getstatus = $this->entityManager->prepare($selectstatus);
				$getstatus->execute(array() );
				if($getstatus->rowCount() > 0 ):
					while( $result = $getstatus->fetchObject()):
						$select .= '<option value="'.$result->role_id.'">'.$result->role_name .' </option>';
					endwhile;
				else:
					$select = '<option value="">No Record Found</option>';
				endif;
			} catch (Throwable $ex) {
				$select = '<option value="">No Record Found</option>';
			}
		}
		return $select;
	}
		
	function selectDutTypeView(){
		$select = "";
		$rolesLoaded = false;
		try {
			$dutype = "SELECT * from acd_tbluser WHERE 1 " ;
			$querydutype = $this->entityManager->prepare($dutype);
			$querydutype->execute(array( ) );
			if($querydutype->rowCount() > 0 ):
				while( $result = $querydutype->fetchObject()):
					$select .= '<option value="'.$result->ID.'">'.$result->access.' </option>';
				endwhile;
				$rolesLoaded = true;
			endif;
		} catch (Throwable $e) {}

		if (!$rolesLoaded) {
			try {
				$dutype = "SELECT * from roles " ;
				$querydutype = $this->entityManager->prepare($dutype);
				$querydutype->execute(array( ) );
				if($querydutype->rowCount() > 0 ):
					while( $result = $querydutype->fetchObject()):
						$select .= '<option value="'.$result->role_id.'">'.$result->role_name.' </option>';
					endwhile;
				else:
					$select = '<option value="">No Record Found</option>';
				endif;
			} catch (Throwable $ex) {
				$select = '<option value="">No Record Found</option>';
			}
		}
		return $select;
	}
	function selectofficeCategoryView(){
		$select = "";
	$offcat = "SELECT * from hr_office_category WHERE offCatStatus = ? " ;
										
	$querydutype = $this->entityManager->prepare($offcat);
	
	$querydutype->execute(array( 1 ) );
	
	if($querydutype->rowCount() > 0 ):
	
		while( $result = $querydutype->fetchObject()):
	 
			$select .= '<option value="'.$result->officeCatID.'">'.$result->officeDesc.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	
	
	function selectMajorofficesView(){
		$select = "";
	$offcat = "SELECT * from hr_major_offices WHERE offStatus = ? " ;
										
	$querydutype = $this->entityManager->prepare($offcat);
	
	$querydutype->execute(array( 1 ) );
	
	if($querydutype->rowCount() > 0 ):
	
		while( $result = $querydutype->fetchObject()):
	 
			$select .= '<option value="'.$result->officeCode.'" >'.$result->officeName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	


function selectdepartmentView(){
		$select = "";
	$offcat = "SELECT * from hr_departments WHERE deptStatus = ? " ;
										
	$querydutype = $this->entityManager->prepare($offcat);
	
	$querydutype->execute(array( 1 ) );
	
	if($querydutype->rowCount() > 0 ):
	
		while( $result = $querydutype->fetchObject()):
	 
			$select .= '<option value="'.$result->deptID.'">'.$result->deptName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
function selectGradeLevelView(){
		$select = "";
	$offcat = "SELECT * from hr_grade_level WHERE gradeStatus = ? " ;
										
	$querydutype = $this->entityManager->prepare($offcat);
	
	$querydutype->execute(array( 1 ) );
	
	if($querydutype->rowCount() > 0 ):
	
		while( $result = $querydutype->fetchObject()):
	 
			$select .= '<option value="'.$result->gradeCode.'">'.$result->GradeName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
	function selectGradeStepView(){
		$select = "";
	$offcat = "SELECT * from hr_grade_step WHERE StepStatus = ? " ;
										
	$querydutype = $this->entityManager->prepare($offcat);
	
	$querydutype->execute(array( 1 ) );
	
	if($querydutype->rowCount() > 0 ):
	
		while( $result = $querydutype->fetchObject()):
	 
			$select .= '<option value="'.$result->stepCode.'">'.$result->StepName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	
		
	function selectSalaryStructureView(){
		$select = "";
	$offcat = "SELECT * from hr_salary_structure WHERE salary_status = ? " ;
										
	$querydutype = $this->entityManager->prepare($offcat);
	
	$querydutype->execute(array( 1 ) );
	
	if($querydutype->rowCount() > 0 ):
	
		while( $result = $querydutype->fetchObject()):
	 
			$select .= '<option value="'.$result->salaryCode.'" >'.$result->salary_Description.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	
		
	function selectDesignationsView(){
		$select = "";
	$offcat = "SELECT * from hr_designations WHERE designStatus = ? " ;
										
	$querydutype = $this->entityManager->prepare($offcat);
	
	$querydutype->execute(array( 1 ) );
	
	if($querydutype->rowCount() > 0 ):
	
		while( $result = $querydutype->fetchObject()):
	 
			$select .= '<option value="'.$result->designatedCode.'" >'.$result->designationDescription.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}	
			
		
		
function selectActiveStatusView(){
		$select = "";
	$selectstatus = "SELECT * from active_status  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->statusID.'">'.$result->StatusName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}

function selectAllGenderView(){
		$select = "";
	$selectstatus = "SELECT * from hr_gender  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->genderID.'">'.$result->genderName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		function selectAllStaffCategoryView(){
		$select = "";
	$selectstatus = "SELECT * from hr_staff_category WHERE catStatus = '1' " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->staff_categoryID.'">'.$result->categoryName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		
		function selectAllStaffRankView($officeID){
		$select = "";
	$selectstatus = "SELECT * from hr_staff_cader WHERE officeID = '$officeID' AND caderStatus = '1' " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->caderID.'">'.$result->caderDesc .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		
		
		function selectAllPersonalItemView(){
		$select = "";
	$selectstatus = "SELECT * from hr_personal_payroll_items WHERE itemStatus = '1'  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->itemCode.'">'.$result->itemName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
function selectAllSalaryItemView(){
		$select = "";
	$selectstatus = "SELECT * from hr_payroll_items WHERE itemStatus = '1'  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->itemCode.'">'.$result->itemName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		
		
		
		function office_subofficesView(){
		$select = "";
	$selectofices = "SELECT * from office_officecategory WHERE officeStatus = '1'  " ;
								
	$getoffices = $this->entityManager->prepare($selectofices);
	
	$getoffices->execute(array( ) );
	
	if($getoffices->rowCount() > 0 ):
	
		while( $result = $getoffices->fetchObject()):
	 
			$select .= '<option value="'.$result->categoryID.'">'.$result->officeCategoryName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		
		function subofficesView(){
		$select = "";
	$selectsubofices = "SELECT * from office_suboffices WHERE subStatus = '1'  " ;
								
	$getsuboffices = $this->entityManager->prepare($selectsubofices);
	
	$getsuboffices->execute(array( ) );
	
	if($getsuboffices->rowCount() > 0 ):
	
		while( $result = $getsuboffices->fetchObject()):
	 
			$select .= '<option value="'.$result->categoryID.'">'.$result->officeCategoryName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		
function selectAllDeductionItemView(){
		$select = "";
	$selectstatus = "SELECT * from hr_payroll_deduction_items WHERE itemStatus = '1'  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->itemCode.'">'.$result->itemName .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
function selectAllReasonForDeActivatingStaffView(){
		$select = "";
	$selectstatus = "SELECT * from hr_reason_for_deactivating_staff WHERE reasonStatus = '1'  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( ) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->reasonID.'">'.$result->reasonDescription .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		function selectFolderView(){
		$select = "";
	$folders = "SELECT MIN(folderid) AS folderid, fname, MIN(folderstatus) AS folderstatus FROM setup_folder GROUP BY fname" ;
										
	$querytitle = $this->entityManager->prepare($folders);
	
	$querytitle->execute();
	
	if($querytitle->rowCount() > 0 ):
	
		while( $result = $querytitle->fetchObject()):
	 
			$select .= '<option value="'.$result->folderid.' ">'.$result->fname.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
		
		
		/*
		function selectSupplementaryView(){
		$select = "";
	$selectstatus = "SELECT * from hr_salutation WHERE `titlestatus` = ?  " ;
								
	$getstatus = $this->entityManager->prepare($selectstatus);
	
	$getstatus->execute(array( 1) );
	
	if($getstatus->rowCount() > 0 ):
	
		while( $result = $getstatus->fetchObject()):
	 
			$select .= '<option value="'.$result->id.'">'.$result->Salutation .' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
	
		}
		
		
	

	

		
*/
		

		
		/*	
function selectPagesByTabsView(){
		$select = "";
		$tabcode = (isset($_GET['tabcode'])&&!empty($_GET['tabcode']))?$_GET['tabcode']:"";
	$pagestabs = "SELECT * from `setup_file_menus` WHERE tabcode = ? AND status = ?" ;
										
	$querpages = $this->entityManager->prepare($pagestabs);
	
	$querpages->execute( array( $tabcode, 1 ) );
	//echo $querpages->rowCount()." Try me";
	if($querpages->rowCount() > 0 ):
	
		while( $pageresult = $querpages->fetchObject()):
	 
			$select .= '<option value="'.$pageresult->menucode.'">'.$pageresult->file_des.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
function selectAllBootstrapView(){
		$select = "";
	$bootstrap = "SELECT * from `bootstrap_css_images` " ;
										
	$querbootstrap = $this->entityManager->prepare($bootstrap);
	
	$querbootstrap->execute();
	
	if($querbootstrap->rowCount() > 0 ):
	
		while( $iconresult = $querbootstrap->fetchObject()):
	 
			$select .= '<option value="'.$iconresult->bootimages.'">'.$iconresult->bootdesc.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
function selectServiceSchemView(){
	$select = "";
	 $postcategory =(isset($_GET['statffCategory'])&&!empty($_GET['statffCategory']))?$_GET['statffCategory']:"";
	 $status = "1";
	$rank = "SELECT * from hr_schemeofservice_setup WHERE EmpCategory  = ? AND schemstatus = ? ";
	$queryrank = $this->entityManager->prepare($rank);
	//$queryrank->execute();
	$queryrank->execute(array( $postcategory, $status ));
	
	if($queryrank->rowCount() > 0)
	{
	while( $result = $queryrank->fetchObject())
	{   
	$select .= '<option value="'.$result->RankCode.'">'.$result->RankName."(".$result->salaryCode." - ".$result->gradeCod.")".'</option>';
	}
	}else{

	$select = '<option value="">No Record Found</option>';

	}		
	
	return $select;
	
}

	function selectSectionCategoryView(){
		$select = "";
	$sqls = "SELECT * from hr_employmentcategory WHERE status = ? ";
		$querys = $this->entityManager->prepare($sqls);
		$status = "1";
		$querys->execute(array( $status ));
		$select = '<option value="">-- Select Option -- </option>';
		if($querys->rowCount() > 0)
		{
		while($results = $querys->fetchObject())
		{   
		$select .= '<option value="'.htmlentities($results->EmploymentCatCode).'">'.htmlentities($results->EmploymentCatName).'</option>';
		 }
		 }else{

	$select = '<option value="">No Record Found</option>';

	}		
	
	return $select;

	}
	
	function getGradeLevelBySalaryCodeView(){
		$select = "";
		 $salarycode =(isset($_GET['salarycode'])&&!empty($_GET['salarycode']))?$_GET['salarycode']:"";
	$sqls = "SELECT * from hr_schemeofservice_setup WHERE schemstatus = ? AND salaryCode = ? ";
		$querys = $this->entityManager->prepare($sqls);
		$status = "1";
		$querys->execute(array( $status, $salarycode ));
		$select = '<option value="">-- Select Option -- </option>';
		if($querys->rowCount() > 0)
		{		
		while($results = $querys->fetchObject()){
			
		$select .= '<option value="'.htmlentities($results->gradeCod).'">'.htmlentities($results->gradeCod).'</option>';
		 }
		 }else{

	$select = '<option value="">No Record Found</option>';	
			
		}
		   
		
		 
		
	return $select;

	}
function selectSalaryStructureView(){
		$select = "";
		
	$sqls = "SELECT * from hr_salarystructure WHERE structurestatus  = ? ";
		$querys = $this->entityManager->prepare($sqls);
		$status = "1";
		$querys->execute(array( $status ));
		$select = '<option value="">-- Select Option -- </option>';
			if($querys->rowCount() > 0)
		{	
		while($results = $querys->fetchObject()){
			
		$select .= '<option value="'.htmlentities($results->structureCode).'">'.htmlentities($results->salaryName).'</option>';
		 }
		 }else{

	$select = '<option value="">No Record Found</option>';	
			
		}
		   
 
	return $select;

	}
function selectDefaultStepcodeView(){
		$select = "";
		$status = "1";
	$sqls = "SELECT * from  hr_default_steps WHERE stepstatus = ? ";
		$querys = $this->entityManager->prepare($sqls);
		
		$querys->execute(array( $status ));
		$select = '<option value="">-- Select Option -- </option>';
			if($querys->rowCount() > 0)
		{	
		while($results = $querys->fetchObject()){
			
		$select .= '<option value="'.htmlentities($results->stepcode).'">'.htmlentities($results->stepcode).'</option>';
		 }
		 }else{

	$select = '<option value="">No Record Found</option>';	
			
		}
		   

	return $select;

	}
	
function selectDefaultDepartmentsView(){
		$select = "";
		$status = "1";
	$sqls = "SELECT * from  acd_department WHERE status = ? ";
		$querys = $this->entityManager->prepare($sqls);
		
		$querys->execute(array( $status ));
		$select = '<option value="">-- Select Option -- </option>';
			if($querys->rowCount() > 0)
		{	
		while($results = $querys->fetchObject()){
			
		$select .= '<option value="'.htmlentities($results->deptcode).'">'.htmlentities($results->deptname).'</option>';
		 }
		 }else{

	$select = '<option value="">No Record Found</option>';	
			
		}
		   

	return $select;

	}
function selectAllProgrammesView(){
		$select = "";
		$status = "1";
	$sqls = "SELECT * from  programmes WHERE prog_status = ? ";
		$querys = $this->entityManager->prepare($sqls);
		
		$querys->execute(array( $status ));
		
			if($querys->rowCount() > 0)
		{	
		while($results = $querys->fetchObject()){
			
		$select .= '<option value="'.htmlentities($results->program_id).'">'.htmlentities($results->programme).'</option>';
		 }
		 }else{

	$select = '<option value="">No Record Found</option>';	
			
		}
		   

	return $select;

	}
	function selectAllProgramTypesView(){
		$select = "";
	$menuTables = "SELECT * from program_category" ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($menuTables);
	
	$queryTabs->execute(array( $status ) );
	$select = '<option value="">-- Select Option -- </option>';
	if($queryTabs->rowCount() > 0 ):
	
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->prog_cat_id.'">'.$result->prog_cat_name.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
function selectAllStatusView(){
		$select = "";
		
		$select .= '<option value="1">Active</option>';
		$select .= '<option value="0">Inactive</option>';
		   

	return $select;

	}
function selectAllBasisForApplicationView(){
		$select = "";
		$status = "1";
	$sqls = "SELECT * from  htl_basis_for_accommondation  ";
		$querys = $this->entityManager->prepare($sqls);
		
		$querys->execute(array());
		$select = '<option value="">-- Select Option -- </option>';
			if($querys->rowCount() > 0)
		{	
		while($results = $querys->fetchObject()){
			$geteplod = explode("(",$results->BasisName);
			$fieldName = $geteplod[0];
		$select .= '<option value="'.htmlentities($results->basis_id).'">'.htmlentities($fieldName).'</option>';
		 }
		 }else{

	$select = '<option value="">No Record Found</option>';	
			
		}
		   

	return $select;

	}
function selectAllBlocksView(){
		$select = "";
	$menuTables = "SELECT * from htl_blocks  WHERE blockstatus = ? " ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($menuTables);
	
	$queryTabs->execute(array( $status ) );
	$select = '<option value="">-- Select Option -- </option>';
	if($queryTabs->rowCount() > 0 ):
	
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->block_id.'">'.$result->blockName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
function selectAllLocationsView(){
		$select = "";
	$menuTables = "SELECT * from htl_add_locations  WHERE LocStatus = ? " ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($menuTables);
	
	$queryTabs->execute(array( $status ) );
	$select = '<option value="">-- Select Option -- </option>';
	if($queryTabs->rowCount() > 0 ):
	
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->loc_id.'">'.$result->LocName ."(".$result->NickName.")".' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
function selectAllSessionsView(){
		$select = "";
	$menuTables = "SELECT * from acd_add_session ORDER BY session_id DESC" ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($menuTables);
	
	$queryTabs->execute(array( $status ) );
	$select = '<option value="">-- Select Option -- </option>';
	if($queryTabs->rowCount() > 0 ):
	
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->session_id.'">'.$result->SessionName.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
function selectAllSemestersView(){
		$select = "";
	$menuTables = "SELECT * from semester" ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($menuTables);
	
	$queryTabs->execute(array( $status ) );
	$select = '<option value="">-- Select Option -- </option>';
	if($queryTabs->rowCount() > 0 ):
	
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->id.'">'.$result->name.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
		
function selectAllRoomsView(){
		$select = "";
	$menuTables = "SELECT * from htl_rooms" ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($menuTables);
	
	$queryTabs->execute(array( $status ) );
	//$select = '<option value="">-- Select Option -- </option>';
	if($queryTabs->rowCount() > 0 ):
	
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->Room_id.'">'.$result->RoomNumber.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
function selectAllFacultiesView(){
		$select = "";
	$facuty = "SELECT * from faculties WHERE factstatus = ? " ;
			$status = "1";							
	$queryTabs = $this->entityManager->prepare($facuty);
	
	$queryTabs->execute(array( $status ) );
	//$select = '<option value="">-- Select Option -- </option>';
	if($queryTabs->rowCount() > 0 ):
	
		while( $result = $queryTabs->fetchObject()):
	 
			$select .= '<option value="'.$result->faccode.'">'.$result->facname.' </option>';
		endwhile;
	
		else:
		$select = '<option value="">No Record Found</option>';
		endif;	
	return $select;
		}
public function getFacultiesForSelectView(){
		$output = '<option value="">Select faculties</option>' ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM faculties WHERE factstatus = ?" );
		$getallitems->execute( array( 1 ) ) ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="'. $item->faccode .'">' . $item->facname . '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}
//		Get Departments By Faculty		422
	public function getDepartmentsByFacultyForSelectView(){
		$fatcode = !empty( $_GET['faculty'] ) ? $_GET['faculty'] : "" ;
		if( $fatcode !== "" ):
			$output = '<option value="">Select Department</option>' ;
			$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_department WHERE status = ? AND faccode = ?" );
			$getallitems->execute( array( 1, $fatcode ) ) ;
			if( $getallitems->rowCount() >= 1 ):
				while( $item = $getallitems->fetchObject() ):
					$output .= '<option value="'. $item->deptcode .'">'. $item->deptname .'</option>' ;
				endwhile ;
			else:
				$output = '<option value="">No record found</option>' ;
			endif ;
			return $output ;
		endif ;
	}
//		Get All program_category		
	public function getAllprogram_categoryForSelectView(){
		
			$output = '<option value="">Select Study Type</option>' ;
			$getallitems = $this->entityManager->prepare( "SELECT * FROM program_category WHERE CatStatus = ? " );
			$getallitems->execute( array( 1 ) ) ;
			if( $getallitems->rowCount() >= 1 ):
				while( $item = $getallitems->fetchObject() ):
					$output .= '<option value="'. $item->prog_cat_id .'">'. $item->prog_cat_name .'</option>' ;
				endwhile ;
			else:
				$output = '<option value="">No record found</option>' ;
			endif ;
			return $output ;
		
	}
//		Get All Available Levels		
	public function getAllAvailableLevelsForSelectView(){
		$output = '<option value="">Select Level</option>' ;
		$output .= '<option value="allLevels">All Levels</option>' ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_avalaiblelevel" );
		$getallitems->execute( array(  ) ) ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="'. $item->id .'">' . $item->levelcode . '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}
	//		Get All Available Levels By Study Type
public function getAllAvailableLevelsByProgIdForSelectView(){
	$progId = !empty( $_GET['progid'] ) ? $_GET['progid'] : "" ;
		$output = '<option value="">Select Level</option>' ;
		$output .= '<option value="allLevels">All Levels</option>' ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_avalaiblelevel WHERE prog_id = ? " );
		$getallitems->execute( array( $progId ) ) ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="'. $item->id .'">' . $item->levelcode . '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}
public function getAllOperationalSemestersForSelectView(){
		$output = '<option value="">Select Semester</option>' ;
		$output .= '<option value="allSemesters">All Semesters</option>' ;
		//$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_semester WHERE status = ? AND operational = ?" );
		//$getallitems->execute( array( 1, 1 ) ) ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_semester" );
		$getallitems->execute() ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="'. $item->semestercode .'">' . $item->semestername . '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}	
public function getDeptCourseUnitsView(){
		$output = '' ;
		
	$fatcode = !empty( $_POST['faculty'] ) ? $_POST['faculty'] : "" ;
	$deptcode = !empty( $_POST['department'] ) ? $_POST['department'] : "" ;
	$levelcode = !empty( $_POST['level'] ) ? $_POST['level'] : "" ;
	$semestercode = !empty( $_POST['semester'] ) ? $_POST['semester'] : "" ;

	if( isset($_POST['add']) && $fatcode !== "" && $deptcode !== "" && $levelcode !== "" && $semestercode !== "" ):


		$allavailablecourses = $con->prepare( "SELECT * FROM acd_avalaiblecourses WHERE deptcode = ?  AND semesters = ? AND level = ? " ) ;
		//echo "SELECT * FROM acd_avalaiblecourses WHERE deptcode = ?  AND semesters = ? " ;
		$allavailablecourses->execute( array( $deptcode, $semestercode, $levelcode) ) ;
		$output .=  "<div>Total Count". $allavailablecourses->rowCount()."</div>";
		if( $allavailablecourses->rowCount() >= 1 ):
				$output .=   '<div style="font-size:14px; padding:5px;"><strong>dtydtt Semester</strong></div>' ;
				$output .=   '<table class="table table-striped table-bordered table-hover">' ;
				$output .=   '<thead style="background-color:#CCF;">' ;
				$output .=   '<th>Course Code</th>' ;
				$output .=   '<th>Course Title</th>' ;
				$output .=   '<th class="text-center">Course Unit</th>' ;
				$output .=   '<th class="text-center">Status</th>' ;
				$output .=   '<th class="text-center">Edit</th>' ;
				$output .=   '</thead>' ;
				$output .=   '<tbody>' ;
			while( $course = $allavailablecourses->fetchObject() )://coursecode, coursetitle, courseunit, deptcode, status, level, semesters
					$output .=  '<tr>' ;
					$output .= '<td>' . $course->coursecode . '</td>' ;
					$output .=  '<td>' . $course->coursetitle . '</td>' ;
					$output .=  '<td class="text-center">' . $course->courseunit . '</td>' ;
					$output .=  ( $course->status == 1 ) ? '<td class="text-center"><i class="fa fa-lg fa-check-circle-o text-success"></i> Active</td>' : '<td class="text-center"><i class="fa fa-lg fa-check-circle-o text-danger"></i> Inactive</td>' ;
					$output .=  '<td class="text-center"><a href="#edit" title="Edit" onclick="updateData(this, true)" id="' . $course->coursecode . '"><i class="fa fa-pencil-square"></i></a></td>' ;
					$output .=  '</tr>' ;
			endwhile ;
				$output .=  '</tbody>' ;
				$output .=  '</table>' ;
		//else:
			//$output = '<div style="font-size:14px; padding:5px; color:#F00;"><strong> No Record Found for ' . $deptInfo->description . ' Department</strong></div>' ;
				endif ;
			endif;
							  
	return $output ;
	}
	
//		Get Course of Study by Programme		
	public function getCourseOfStudyByProgramForSelectView(){
		$programmeId = !empty( $_GET['programmeId'] ) ? $_GET['programmeId'] : "" ;
		if( $programmeId !== "" ):
			$output = '<option value="">Select course of study</option>' ;
			
			$getallitems = $this->entityManager->prepare( "SELECT * FROM courselist WHERE program_id = ? " ) ;
			$getallitems->execute( array( $programmeId ) ) ;
			if( $getallitems->rowCount() >= 1 ):
				while( $item = $getallitems->fetchObject() ):
					$output .= '<option value="' . $item->coscode . '">' . $item->cosname . '</option>' ;
				endwhile ;
			else:
				$output = '<option value="">No record found</option>' ;
			endif ;
			return $output ;
		endif ;
	}	
	public function getGenderForSelectView(){
		
			$output = '<option value="">Select Gender</option>' ;
			
			$getallitems = $this->entityManager->prepare( "SELECT * FROM hr_tblsex " ) ;
			$getallitems->execute(  ) ;
			if( $getallitems->rowCount() >= 1 ):
				while( $item = $getallitems->fetchObject() ):
					$output .= '<option value="'.$item->ID.'">' . $item->sex . '</option>' ;
				endwhile ;
			else:
				$output = '<option value="">No record found</option>' ;
			endif ;
			return $output ;
		
	}	
public function getCourseUnitStatusForSelectView(){
		$output = '<option value="">Select status</option>' ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_coursestructure WHERE status = ?" );
		$getallitems->execute( array( 1 ) ) ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="' . $item->ID . '">' . $item->type . '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}
//		Get Active Semester		
	public function getActiveSemestersForSelectView(){
		$output = '<option value="">Select Semester</option>' ;
		//$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_semester WHERE status = ? " );
		//$getallitems->execute( array( 1 ) ) ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_semester" ) ;
		$getallitems->execute() ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="' . $item->semestercode . '">' . $item->semestername . '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}
//	Get Available Levels		
	public function getAvailableLevelsForSelectView(){
		$output = '<option value="">Select Level</option>' ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM acd_avalaiblelevel WHERE status = ?" );
		$getallitems->execute( array( 1 ) ) ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="' . $item->id . '">' . $item->levelcode . '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}
	
	public function getBlockGenderForSelectView(){
		//$locations = !empty( $_GET['locations'] ) ? $_GET['locations'] : "" ;
		$output = '<option value="">Select Block</option>' ;
		$getallitems = $this->entityManager->prepare( "SELECT * FROM htl_bind_block_location  t JOIN htl_blocks b ON(b.block_id=t.block_id) WHERE t.Status = ? " );
		$getallitems->execute( array( 1 ) ) ;
		if( $getallitems->rowCount() >= 1 ):
			while( $item = $getallitems->fetchObject() ):
				$output .= '<option value="' . $item->locationblock . '">' . $item->sexname ." " . $item->blockName.  '</option>' ;
			endwhile ;
		else:
			$output = '<option value="">No record found</option>' ;
		endif ;
		return $output ;
	}
	
//		Get Courses	To Register	
	public function getCourseToRegisterView(){
		//$config = new \Edu\UMM\Config\Configuration() ;
		$output = '' ;
		$programmecode = !empty( $_GET['vProgramme'] ) ? $_GET['vProgramme'] : "" ;
		$courseofstudycode = !empty( $_GET['vCOS'] ) ? $_GET['vCOS'] : "" ;
		$levelcode = !empty( $_GET['vLevel'] ) ? $_GET['vLevel'] : "" ;
		$semestercode = !empty( $_GET['vSemester'] ) ? $_GET['vSemester'] : "" ;
		$csessions = !empty( $_GET['csession'] ) ? $_GET['csession'] : "" ;
		//echo "sem ".$semestercode." sess ". $csessions." levl ". $levelcode." prog ". $programmecode;
		if( $programmecode !== "" && $courseofstudycode !== "" && $levelcode !== "" && $semestercode !== "" && $csessions !== "" ):
			$urlToken = "";
			$studentregcode = $programmecode . $courseofstudycode . $levelcode . $semestercode ;
			$ckctreg = $this->entityManager->prepare( " SELECT * FROM acd_courses_to_register  WHERE SemesterID = ? AND sessionid = ? AND levelid = ? AND programid = ? " ) ;
			$ckctreg->execute( array( $semestercode, $csessions, $levelcode, $courseofstudycode ) ) ;
			
			if( $ckctreg->rowCount() >= 1 ):
				$ctregInfo = $ckctreg->fetchObject() ;
				$mylevel = $this->entityManager->query("SELECT * FROM acd_avalaiblelevel WHERE id = '$levelcode' ")->fetch(PDO::FETCH_ASSOC);
				$mycourseofstudy = $this->entityManager->query("SELECT * FROM courselist WHERE coscode = '$courseofstudycode' ")->fetch(PDO::FETCH_ASSOC);
				$mysemester = $this->entityManager->query("SELECT * FROM acd_semester WHERE semestercode = '$semestercode' ")->fetch(PDO::FETCH_ASSOC);
				$output = '<div style="font-size:14px; padding:5px;" align="center"><strong>' . $mycourseofstudy['cosname'] . ' - ' . $mylevel['levelcode'] . ' COURSES</strong></div>' ;
				$output .= '<div class="row"><div class="col-md-9" style="font-size:14px; padding:5px;"><strong>' . $mysemester['semestername'] . ' SEMESTER</strong></div><div class="col-md-3" align="center"><a href="#">Print Report</a></div></div>' ;
				$output .= '<table class="table table-striped table-bordered table-hover">' ;
				$output .= '<thead style="background-color:#FFF;">' ;
				$output .= '<th>COURSE CODE</th>' ;
				$output .= '<th>COURSE TITLE</th>' ;
				$output .= '<th class="text-center">COURSE UNIT</th>' ;
				$output .= '<th class="text-center">PRE-REQUISITE(S)</th>' ;
				$output .= '<th class="text-center">STATUS</th>' ;
				$output .= '<th class="text-center">DELETE</th>' ;
				$output .= '</thead>' ;
				$output .= '<tbody>' ;
				$allctreg = $this->entityManager->prepare(  "SELECT * FROM acd_courses_to_register a JOIN acd_avalaiblecourses c ON(a.Courseid=c.ID)  WHERE a.SemesterID = ? AND a.sessionid = ? AND a.levelid = ? AND a.programid = ? "  ) ;
				$allctreg->execute( array(  $semestercode, $csessions, $levelcode, $courseofstudycode  ) ) ;
				$cStatus = "" ; $totalCoreUnits = 0 ; $tCount = $allctreg->rowCount() ; $counter = 0 ; $counter2 = 0 ;
				while( $ctreg = $allctreg->fetchObject() ):
					$counter ++ ;
					$totalCoreUnits += $ctreg->unit ;
					$output .= '<tr>' ;
					$output .= '<td>' . $ctreg->coursecode . '</td>' ;
					$output .= '<td>' . $ctreg->coursetitle . '</td>' ;
					$output .= '<td class="text-center">' . $ctreg->courseunit . '</td>' ;
					$output .= '<td class="text-center">' . "" . '</td>' ;
					$output .= ( $ctreg->coursetypeid == 1 ) ? '<td class="text-center"><i class="fa fa-lg fa-check-circle-o text-success"></i> Active</td>' : '<td class="text-center"><i class="fa fa-lg fa-check-circle-o text-danger"></i> Inactive</td>' ;
					$output .= '<td class="text-center"><a href="#Delete" title="Edit" onclick="removeData(this)" id="' . $ctreg->ID . '"><i class="fa fa-lg fa-times-circle text-danger"></i></a></td>' ;
					$output .= '</tr>' ;
					if( ( strtoupper( trim( $ctreg->coursetypeid ) ) === "1" ) && $counter == $tCount ):
						$output .= '<tr><td colspan="2" class="text-center"><b>TOTAL</b></td><td class="text-center"><b>' . $totalCoreUnits . '</b></td><td colspan="3">&nbsp;</td></tr>' ;
					endif ;
				endwhile ;
				$output .= '</tbody>' ;
				$output .= '</table>' ;
			else:
				$output = 'There is no course registration setup for this criteria.' ;
			endif ;
		else:
			$output = 'Please select all the required options' ;
		endif ;
		return $output ;
	}
function displayInovoiceItemView(){
		$viewId = !empty( $_GET['viewId'] ) ? $_GET['viewId'] : "" ;
		
		$output = '';
		$output .= '<table cellpadding="0" cellspacing="0" border="0" class="table  table-bordered table-striped" >
                             
								<!---<p><a href="add_prog.php" class="btn btn-success"><i class="icon-plus"></i>&nbsp;Add Courses of Study</a></p>-->
							
                                <thead>
                                    <tr>
                       
                                        <th>S/N</th> 
										<th>Item Description</th>										
                                        <th>Amount</th>
										 </tr>
                                </thead>
                                <tbody>';
								 
                                 
                                  $n=0;
								  $totalamount = 0;
                                   $feeitemtype = $this->entityManager->query("select * FROM pay_itemsemester_invoice  WHERE sessionSemesterInvoiceID = '$viewId' ");
								   
                                    while($getfeeitemtype = $feeitemtype->fetch(PDO::FETCH_ASSOC)){
                                        $n++;
										$totalamount +=$getfeeitemtype['Amount'];
                                  
                                  
									$output .= '<tr class="">';
									
                                    $output .= '<td>'. $n.'</td>';
									$output .= '<td class="">'.$getfeeitemtype['ItemName'].'</td>';
                                    $output .= '<td class="">'.number_format($getfeeitemtype['Amount'],2).'</td> ';
									
                                    $output .= '</tr>';
									 } 
							$output .= '<tr>';
							$output .= '<td class=""></td> ';
							$output .= '<td class="">Total</td>';
							$output .= '<td class="">'. number_format($totalamount,2).'</td>';
                                    $output .= '</tr>
                                </tbody>
                            </table>';
		return $output;
		
	}	*/
}
?>