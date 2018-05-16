<?php
namespace Si\Resourcespage\Controller\Adminhtml\document;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as directorycreate;
use Magento\Framework\Filesystem;

class Requests extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var \Magento\Framework\Controller\Result\ForwardFactory
     */
    protected $_resultForwardFactory;
	
	/**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $_directoryList;
	
	/**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $_directoryCreate;
	
	/**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $htmltree;
	
	/**
	* @var Magento\Framework\Filesystem
	*/
	
	protected $_filesystem;
	
	protected $_imageFactory;

    /**
     * @param Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     @param \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory,
		directorycreate $directorycreate,
		Filesystem $fileSystem,
		DirectoryList $DirectoryList,
		\Magento\Framework\Image\AdapterFactory $imageFactory
    ) {
		$this->_imageFactory = $imageFactory;
		$this->_filesystem = $fileSystem;
        $this->_resultJsonFactory    = $resultJsonFactory;
        $this->_resultForwardFactory = $resultForwardFactory;
        $this->_directoryList = $DirectoryList;
        $this->_directoryCreate = $directorycreate;
        return parent::__construct($context); 
    }

    /**
     * Ajax action
     *
     * @return \Magento\Framework\Controller\Result\JsonFactory|\Magento\Framework\Controller\Result\ForwardFactory
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
		$basedir = $this->_directoryList->getPath("media") . DIRECTORY_SEPARATOR . 'medialibrary';
		$basedirthumb = $this->_directoryList->getPath("media") . DIRECTORY_SEPARATOR . 'thumb_medialibrary';
		$data = $this->getRequest()->getParams();
		$datar['status'] = 0;
		
		
			/******************************Search Files****************************************/
			if(isset($data['action']) && $data['action'] == "searchfile"){
				$datar['status'] = 1;
				$datar['files'] = $this->searchcontent($basedir);
			}
			/******************************Search Files****************************************/
			
			/******************************Folder Permission****************************************/
			if(isset($data['action']) && $data['action'] == "folderpermission"){
			
				$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
				$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
				$connection = $resource->getConnection();
				 
				 $folder_path = $data['folder_path'];
				 $customer_group = $data['customer_group'];
				//Insert Data into table
				$sql = "REPLACE INTO folder_permission(`id`, `folder_path`, `customer_group` ) VALUES(NULL, '{$folder_path}', '{$customer_group}')";
				$connection->query($sql);
				$datar['status'] = 1;
				$datar['groups'] = $this->getfolderpermissions($folder_path);
				
			}
			/******************************Folder Permission****************************************/
		
			/***************************File Delete **************************************/
			if(isset($data['action']) && $data['action'] == "deletefile"){
			
				//$datar['status'] = $this->deletefile($this->_directoryList->getRoot(). DIRECTORY_SEPARATOR . $data['delete_file']);
				foreach(explode(',',$data['delete_file']) as $item){
					$datar['status'] = $this->deletefile($this->_directoryList->getRoot(). DIRECTORY_SEPARATOR . $item);
				}
				$path = $basedir . DIRECTORY_SEPARATOR . $data['dir'];
				$datar['files'] = $this->scanFiles($path, $data['dir']);
			}
			/***************************File Delete **************************************/
			
		
			/*************************** File upload *************************************/
			if(isset($data['action']) && $data['action'] == "fileupload" ){
			if($data['uploadtype'] == "thumbnail"){
				$uploaddir = $basedirthumb . DIRECTORY_SEPARATOR . $data['dir'] . DIRECTORY_SEPARATOR ;
			}else{
				$uploaddir = $basedir . DIRECTORY_SEPARATOR . $data['dir'] . DIRECTORY_SEPARATOR ;
			}
			foreach($_FILES as $key => $values ){
			$file = $_FILES[$key]["tmp_name"];
			$name = $_FILES[$key]['name'];
			$path = $basedir . DIRECTORY_SEPARATOR . $data['dir'];
			
			if(!file_exists($uploaddir)){
				mkdir("$uploaddir", 0777, true);
			}
			//echo $uploaddir; die;
			move_uploaded_file( $file, $uploaddir.$name);
			}
			$datar['status'] = 1;
			$datar['files'] = $this->scanFiles($path, $data['dir']);
			
			}
		
		/*************************** File upload *************************************/
		
		/***************** Create Directry *********************/
		if(isset($data['action']) && isset($data['dir']) && isset($data['folder_name']) && $data['action'] == 'createdir')
		{
			$path = $basedir . DIRECTORY_SEPARATOR . $data['dir'] . DIRECTORY_SEPARATOR . $data['folder_name'];
			$datar['status'] = $this->createDir($path);
			$path = $basedir;
			$array = $this->scanDirectory($path);
			$datar['menu'] = $this->menutree($array, null, 1);
		}
		
		
		/***************** Scan Files *************************/
		if(isset($data['action']) && isset($data['dir']) && $data['action'] == 'scanfile')
		{
			$path = $basedir . DIRECTORY_SEPARATOR . $data['dir'];
			$datar['status'] = 1;
			$datar['files'] = $this->scanFiles($path, $data['dir']);
			$datar['groups'] = $this->getfolderpermissions($data['dir']);
		}
		
		
		
		/***************** Scan Directory *************************/
		if(isset($data['action']) && $data['action'] == 'scandir')
		{
			$path = $basedir;
			$array = $this->scanDirectory($path);
			$datar['status'] = 1;
			$datar['menu'] = $this->menutree($array, null, 1);
		}
		
		/***************** Scan Files *************************/
		if(isset($data['action']) && isset($data['old_dir']) && isset($data['new_dir']) && $data['action'] == 'renamedir')
		{
			$datar['status'] = $this->renamefolder($basedir . DIRECTORY_SEPARATOR . $data['old_dir'], $data['new_dir']);
			$path = $basedir;
			$array = $this->scanDirectory($path);
			$datar['menu'] = $this->menutree($array, null, 1);
		}
		
		/***************** Scan Files *************************/
		if(isset($data['action']) && isset($data['rmdir']) && $data['action'] == 'removedir')
		{
			$datar['status'] = $this->removedir($basedir . DIRECTORY_SEPARATOR . $data['rmdir']);
			$path = $basedir;
			$array = $this->scanDirectory($path);
			$datar['menu'] = $this->menutree($array, null, 1);
		}
		
		
				
		/* echo "<pre>";
		
		print_r($result);
		//print_r(scandir($this->_directoryList->getPath("media"))); 
		//print_r( $this->dirToArray($this->_directoryList->getPath("media")) );
		die; */
		
		
		$result = $this->_resultJsonFactory->create();
		return $result->setData($datar);	
					
		$this->_view->loadLayout();
        $this->_view->renderLayout();
    }
	
	
	/**
	* Scan fils
	* @Return files array
	*/
	function scanFiles($dir, $path) 
	{ 
		$imagestype = ['jpg', 'jpeg', 'gif', 'png'];

		$result = array(); 

		$cdir = scandir($dir);
			$count = 0;
			foreach ($cdir as $key => $value) 
			{ 
				if (!in_array($value,array(".",".."))) 
				{ 
					if (! is_dir($dir . DIRECTORY_SEPARATOR . $value)) 
					{ 
						
						$result[$count]['mfile'] = 'pub'. DIRECTORY_SEPARATOR .'media'. DIRECTORY_SEPARATOR .'medialibrary'. DIRECTORY_SEPARATOR .$path. DIRECTORY_SEPARATOR . $value; 
						
						$thumbnail = 'thumb_medialibrary'. DIRECTORY_SEPARATOR .$path. DIRECTORY_SEPARATOR . $value; 
						if(in_array(explode('.',$value)[1], $imagestype)){
							$result[$count]['thumbnail'] = 'pub'. DIRECTORY_SEPARATOR .'media'. DIRECTORY_SEPARATOR .'medialibrary'. DIRECTORY_SEPARATOR .$path. DIRECTORY_SEPARATOR . $value;
						}else{
							$thumbnail = 'thumb_medialibrary'. DIRECTORY_SEPARATOR .$path. DIRECTORY_SEPARATOR . explode('.',$value)[0] . '.png'; 
							if(file_exists($this->_directoryList->getPath("media"). DIRECTORY_SEPARATOR .$thumbnail)){
								$result[$count]['thumbnail'] = 'pub'. DIRECTORY_SEPARATOR .'media'. DIRECTORY_SEPARATOR .'thumb_medialibrary'. DIRECTORY_SEPARATOR .$path. DIRECTORY_SEPARATOR . explode('.',$value)[0] . '.png'; 
							}else{
								//print_r(explode('.',$value)); die;
								$result[$count]['thumbnail'] = "pub/media/" . explode('.',$value)[1]. ".png";
								//$result[$count]['thumbnail'] = $this->_directoryList->getPath("media").$thumbnail;
							}
						}
						$count++;
					} 
				} 
			} 

		return $result; 
	} 
	
	/**
	* Scan fils
	* @Return files array
	*/
	/*  function scanDirectory($dir) 
	{ 

		$result = array(); 

		$cdir = scandir($dir); 
			foreach ($cdir as $key => $value) 
			{ 
				if (!in_array($value,array(".",".."))) 
				{ 
					if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) 
					{ 
						$result[$value] = $this->scanDirectory($dir . DIRECTORY_SEPARATOR . $value);
					} 
				} 
			} 

		return $result; 
	} */  
	
	function scanDirectory($dir) 
	{ 
		$result = array();
		
		$cdir = scandir($dir); 
		foreach ($cdir as $key => $value) 
		{ 
		  if (!in_array($value,array(".",".."))) 
		  { 
			 if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) 
			 { 
				$result[$value] = $this->scanDirectory($dir . DIRECTORY_SEPARATOR . $value); 
			 } 
			 else 
			 { 
				//$result[] = $value; 
			 } 
		  } 
		}
		
		return $result; 
	} 
	
	
	/*
	* Create directory
	* @Return directory array
	*/	
	function createDir($dir){
		return $this->_directoryCreate->mkdir($dir, 0775);   
	}
	
	function menutree($tree, $parent = null, $class=null) {
	if($class != null){
	$out = "<ul class='intranet' id='expList' >";
	}else{
	$out = "<ul>";
	}
		foreach($tree as $key => $value) {
			if (is_array($value) && count($value)) {
				$parentid = $parent.$key.'/';
				$out.= "<li class='intranet-folder' >";
				
				$out .= "<a href='#' data-dir='$parent$key' class='medialib'>";
				
				$out .= $key;
				$out .= "</a>";
				$out .= $this->menutree($value,$parentid);
				$out.= "</li>";
			} else {
				$out.= "<li>";
				$out .= "<a href='#'  data-dir='$parent$key' class='medialib' >";
				
				$out .= $key;
				$out .= "</a>";
				$out.= "</li>";
			}

		}

		$out.= "</ul>";

		return $out;
	}
	
	/*
	* Rename folder
	*
	*/
	
	function  renamefolder($old_dir, $newdir){
			
			$result = 1;
			if(is_dir($old_dir)){
				rename($old_dir, dirname($old_dir). DIRECTORY_SEPARATOR .$newdir);
				$result = 1;
			}else{
				$result = 0;
			}
		
		
		return $result;
	}
	
	
	function removedir($src) {
	$dir = opendir($src);
	while(false !== ( $file = readdir($dir)) ) {
		if (( $file != '.' ) && ( $file != '..' )) {
			$full = $src . '/' . $file;
			if ( is_dir($full) ) {
				$this->removedir($full);
			}
			else {
				unlink($full);
			}
		}
	}
	closedir($dir);
	rmdir($src);
	return true;
	}
	
	
	 /**
     * Upload and save image
     * 
     */
    function fileupload()
    {
        $result = array();
        if ($data['documents']['name']) {
            try {
				 $file = $_FILES["documents"]["tmp_name"];
				return move_uploaded_file( $file, $dir . $file);
                /* // init uploader model.
                $uploader = $this->_objectManager->create(
                    'Magento\MediaStorage\Model\File\Uploader',
                    ['fileId' => 'documents']
                );
				
                $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
                $uploader->setAllowRenameFiles(true);
                $uploader->setFilesDispersion(true);
                // get media directory
                $mediaDirectory = $this->_filesystem->getDirectoryRead($dir);
                // save the image to media directory
                $result = $uploader->save($mediaDirectory->getAbsolutePath()); */
            } catch (Exception $e) {
                \Zend_Debug::dump($e->getMessage());
            }
        }
        //return $result;
    }
	
	/**
	* Delete files
	*/
	
	function deletefile($page){
		try{
			unlink($page);
			return true;
		}catch(Exception $e){
			return $e->getMessage();
		}
	}
	
	/**
	*
	*/
	
	function getfolderpermissions($folder_path){
		try{
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
			$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
			$connection = $resource->getConnection();
			 
			
			//Insert Data into table
			$sql = "select * from folder_permission where folder_path = '{$folder_path}'";
			$datar = $connection->fetchAll($sql);
			if(count($datar) > 0 ){
			return $datar[0]['customer_group'];
			}else{
				return -1;
			}
		}catch(Exception $e){
			return -1;
		}
	}
	
	function searchcontent($dir, &$results = array(), $rootdir = null){
    
		if($rootdir==null){
		$rootdir = '';
		}else{
		$rootdir = $rootdir. DIRECTORY_SEPARATOR;
		}
		$files = scandir($dir);
		
		
		foreach($files as $key => $value){
			$path = $dir . DIRECTORY_SEPARATOR . $value ;
			//$files = $rootdir . DIRECTORY_SEPARATOR . $value ;
			if(!is_dir($path)) {
				//$results[] = $files;
				
				$results[] = 'pub'. DIRECTORY_SEPARATOR .'media'. DIRECTORY_SEPARATOR .'medialibrary'. DIRECTORY_SEPARATOR .$rootdir.$value;
				//$results[] = $dir;
				
			} else if($value != "." && $value != "..") {
				$this->searchcontent($path, $results,  $rootdir.$value);
				//$results[] = $path;
			}
		}

		return $results;
	}
	
	
	// pass imagename, width and height
    public function resize($image, $dir, $path,  $width = null, $height = null)
    {
		$thumbdir = $this->_directoryList->getPath("media") . DIRECTORY_SEPARATOR . 'thumb_medialibrary';
        
        //create image factory...
        $imageResize = $this->_imageFactory->create();         
        $imageResize->open($dir. DIRECTORY_SEPARATOR .$image);
        $imageResize->constrainOnly(TRUE);         
        $imageResize->keepTransparency(TRUE);         
        $imageResize->keepFrame(FALSE);         
        $imageResize->keepAspectRatio(TRUE);         
        $imageResize->resize($width,$height);  
        //destination folder                
        $destination = $thumbdir. DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR .$image ;    
        //save image      
        $imageResize->save($destination);         

      
        return true;
    }
	
	
	
	
	
}
