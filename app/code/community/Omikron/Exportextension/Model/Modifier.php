<?php
/**
 * this class adds small functions to modify the export data
 *
 * @copyright  Copyright (c) 2009 Omikron Data Quality GmbH (http://www.omikron.net)
 * @copyright  Copyright (c) 2013 Richard Aspden (http://www.insanityinside.net)
 * @author     Rudolf Batt (rb@omikron.net)
 * @author     Richard Aspden (magento@insanityinside.net)
 */


class Omikron_Exportextension_Model_Modifier extends Mage_Dataflow_Model_Convert_Parser_Abstract
{	
	private $_categoryPathCache;
	private $_categoryFieldName;
	private $_categoryDelimiter;
	private $_categoryPathDelimiter;
	private $_firstCategoryLevel;
	private $_childSkuFieldName;
	private $_childSkuDelimiter;
	private $_parentSkuFieldName;
	private $_configurableAttributesFieldName;
	private $_configurableAttributesDelimiter;
	private $_galleryImageURLFieldName;
	private $_galleryImageURLDelimiter;
	private $_galleryImageURLFull;
	
	protected function _removeHtmlTags(&$row)
	{
		foreach ($row AS $key => $value) {
			$row[$key] = preg_replace('#</?.*?>#', ' ', $value);
		}
	}
	
	/**
	 * cleans content from setted strings
	 */
	protected function _removeLineBreaks(&$row)
	{
		foreach ($row AS $key => $value) {
			$row[$key] = str_replace(array("\r", "\n"), '', $value);
		}
	}
	
	/**
	 * adds categories to the row
	 */
	protected function _addCategories(&$row, &$product)
	{
		$categoryFieldName = $this->_getCategoryFieldName();
		$categoryDelimiter = $this->_getCategoryDelimiter();
		
		$row[$categoryFieldName] = '';
        $tempCatPath = '';
		foreach($product->getCategoryIds() as $categoryId){
            //dont add delimiter if previous category path was empty or no category was added yet
			if( $tempCatPath != '' && $row[$categoryFieldName] != '') {
				$row[$categoryFieldName] .= $categoryDelimiter;
			}
			
            $tempCatPath = $this->_getCategoryPath($categoryId);
			if ($tempCatPath != '') {
                $row[$categoryFieldName] .= $tempCatPath;
            }
		}
	}
	
	protected function _addParentSkus(&$row, &$product)
	{
		$parentSkuFieldName = $this->_getParentSkuFieldName();

		list($parentID) = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
		if ($parentID != null) {
			$row[$parentSkuFieldName] = Mage::getModel('catalog/product')->load($parentID)->getSku();
		} else {
			$row[$parentSkuFieldName] = '';
		}
	}
	
	protected function _addChildSkus(&$row, &$product)
	{
		$childSkuFieldName = $this->_getChildSkuFieldName();
		$childSkuDelimiter = $this->_getChildSkuDelimiter();
		$row[$childSkuFieldName] = '';

		// Check to see if configurable
		if ($product->getTypeId() == "configurable") {
			$childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$product);
			$childSkuArray = array();
			foreach($childProducts AS $childProduct) {
				$childSkuArray[] = $childProduct->getSku();
			}
			$row[$childSkuFieldName] = implode($childSkuDelimiter,$childSkuArray);
		}
	}
	
	protected function _addConfigurableAttributes(&$row, &$product)
	{
		$configurableAttributesFieldName = $this->_getConfigurableAttributesFieldName();
		$configurableAttributesDelimiter = $this->_getConfigurableAttributesDelimiter();

		// See if a child product
		list($parentID) = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
		if ($parentID != null) {
			// Do something to get the attributes
			$row[$configurableAttributesFieldName] = $product->debug();
		} else {
			$row[$configurableAttributesFieldName] = 'test';
		}

	}

	protected function _addGalleryImageURLs(&$row, &$product)
	{
		$galleryImageURLFieldName = $this->_getGalleryImageURLFieldName();
		$galleryImagesDelimiter = $this->_getGalleryImageURLDelimiter();
		$galleryImageURLFull = $this->_getGalleryImageURLFull();
		
		$mediaGallery = $product->getMediaGallery();
		$mediaGallery = $mediaGallery['images'];
		$galleryImageURLs = array();
		$_baseurl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/';
		
		foreach ($mediaGallery as $galleryImage) {
			if (!$galleryImage['disabled']) {
				if ($galleryImageURLFull != false) {
					if (substr($_baseurl,-1) == '/' && substr($galleryImage['file'],0,1) == '/') { // Clean up double slashes
						$galleryImageURLs[] = $_baseurl.str_replace('//','/',substr($galleryImage['file'],1));
					} else {
						$galleryImageURLs[] = $_baseurl.str_replace('//','/',$galleryImage['file']);
					}
				} else {
					$galleryImageURLs[] = str_replace('//','/',$galleryImage['file']);
				}
			}
		}

		$row[$galleryImageURLFieldName] = implode($galleryImagesDelimiter,$galleryImageURLs);
	}
	
	/**
	 * modifies each data
	 */
	public function unparse()
	{
		$addCategories         = $this->getVar('add_categories', '') == 'true' ? true : false;
		$removeLineBreaks      = $this->getVar('remove_line_breaks', '') == 'true' ? true : false;
		$removeHtmlTags        = $this->getVar('remove_html_tags', '') == 'true' ? true : false;
		
		$addAbsoluteUrlToField = $this->getVar('add_absolute_url_to_field', '');
		if (empty($addAbsoluteUrlToField)) {
			$addAbsoluteUrlToField = false;
		}
		
		$addImageUrlToField = $this->getVar('add_image_url_to_field', '');
		if (empty($addImageUrlToField)) {
			$addImageUrlToField = false;
		}
		
		$addParentSku = $this->getVar('add_parent_sku', '');
		if (empty($addParentSku)) {
			$addParentSku = false;
		}
		
		$addChildSku = $this->getVar('add_child_sku', '');
		if (empty($addChildSku)) {
			$addChildSku = false;
		}
		
		$addConfigurableAttributes = $this->getVar('add_configurable_attributes', '');
		if (empty($addConfigurableAttributes)) {
			$addConfigurableAttributes = false;
		}

		$addGalleryImageURLs = $this->getVar('add_gallery_image_urls', '');
		if (empty($addGalleryImageURLs)) {
			$addGalleryImageURLs = false;
		}

		if (!$addCategories && !$removeLineBreaks && !$removeHtmlTags && !$addParentSku && !$addChildSku && !$addGalleryImageURLs && !$addConfigurableAttributes) {
			$this->addException("no modifier activated!", Varien_Convert_Exception::NOTICE);
			return $this;
		}
		
		//init
        $batchExport    = $this->getBatchExportModel();
        $batchExportIds = $batchExport
			->setBatchId($this->getBatchModel()->getId())
			->getIdCollection();
		
		$productIds     = $this->getData();
		$product        = Mage::getModel('catalog/product');
		$productHelper  = Mage::helper('catalog/product');
		$productCounter = 0;
		
		//start modifying data
		foreach ($batchExportIds as $batchExportId) {
            $batchExport->load($batchExportId);
            $row = $batchExport->getBatchData();
			$product->reset();
			$product->load($productIds[$productCounter]);
			
			if ($addCategories) {
				$this->_addCategories($row, $product);
			}
			if ($removeLineBreaks) {
				$this->_removeLineBreaks($row);
			}
			if ($removeHtmlTags) {
				$this->_removeHtmlTags($row);
			}
			if ($addAbsoluteUrlToField !== false) {
				try{
					$_baseurl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
					$row[$addAbsoluteUrlToField] = $_baseurl.$product->getUrlPath();
				} catch (Exception $e) { /* and forget */}
			}
			if ($addImageUrlToField !== false) {
				try{
					$row[$addImageUrlToField] = $productHelper->getImageUrl($product);
				} catch (Exception $e) { /* and forget */}
			}
			
			if ($addParentSku !== false) {
				$this->_addParentSkus($row, $product);
			}

			if ($addChildSku !== false) {
				$this->_addChildSkus($row, $product);
			}
			
			if ($addConfigurableAttributes !== false) {
				$this->_addConfigurableAttributes($row, $product);
			}

			if ($addGalleryImageURLs !== false) {
				$this->_addGalleryImageURLs($row, $product);
			}
			
            $batchExport->setBatchData($row)
				->setStatus(2)
				->save();
			
			if ($addCategories) {
				$this->getBatchModel()->parseFieldList($batchExport->getBatchData());
			}
			
			$productCounter++;
        }
		
		return $this;
	}
	
	public function parse()
	{
		$this->addException("category parser not implemented, only use 'unparse' to modify export data", Varien_Convert_Exception::WARNING);
		return $this;
	}
	
	/**
	 * returns the category fieldname for the export
	 */
	private function _getCategoryFieldName()
	{
		if ($this->_categoryFieldName == null) {
			$this->_categoryFieldName = $this->getVar('category_field_name', 'categories');
		}
		return $this->_categoryFieldName;
	}
	
	/**
	 * returns the category delimiter used at the export
	 */
	private function _getCategoryDelimiter()
	{
		if ($this->_categoryDelimiter == null) {
			$this->_categoryDelimiter = $this->getVar('category_delimiter', '#');
		}
		return $this->_categoryDelimiter;
	}
	
	/**
	 * returns the category path delimiter used at the export
	 */
	private function _getCategoryPathDelimiter()
	{
		if (!$this->_categoryPathDelimiter) {
			$this->_categoryPathDelimiter = $this->getVar('category_path_delimiter', '>');
		}
		return $this->_categoryPathDelimiter;
	}

	private function _getParentSkuFieldName()
	{
		if (!$this->_parentSkuFieldName) {
			$this->_parentSkuFieldName = $this->getVar('add_parent_sku', ',');
		}
		return $this->_parentSkuFieldName;
	}
	
	private function _getChildSkuFieldName()
	{
		if (!$this->_childSkuFieldName) {
			$this->_childSkuFieldName = $this->getVar('add_child_sku', ',');
		}
		return $this->_childSkuFieldName;
	}

	private function _getChildSkuDelimiter()
	{
		if (!$this->_childSkuDelimiter) {
			$this->_childSkuDelimiter = $this->getVar('child_sku_delimiter', ',');
		}
		return $this->_childSkuDelimiter;
	}

	private function _getConfigurableAttributesFieldName()
	{
		if (!$this->_configurableAttributesFieldName) {
			$this->_configurableAttributesFieldName = $this->getVar('add_configurable_attributes', ',');
		}
		return $this->_configurableAttributesFieldName;
	}

	private function _getConfigurableAttributesDelimiter()
	{
		if (!$this->_configurableAttributesDelimiter) {
			$this->_configurableAttributesDelimiter = $this->getVar('configurable_attributes_delimiter', ',');
		}
		return $this->_configurableAttributesDelimiter;
	}
	
	private function _getGalleryImageURLFieldName()
	{
		if (!$this->_galleryImageURLFieldName) {
			$this->_galleryImageURLFieldName = $this->getVar('add_gallery_image_urls', 'gallery_images');
		}
		return $this->_galleryImageURLFieldName;
	}

	private function _getGalleryImageURLDelimiter()
	{
		if (!$this->_galleryImageURLDelimiter) {
			$this->_galleryImageURLDelimiter = $this->getVar('gallery_image_url_delimiter', ';');
		}
		return $this->_galleryImageURLDelimiter;
	}
		
	private function _getGalleryImageURLFull()
	{
		if (!$this->_galleryImageURLFull) {
			$this->_galleryImageURLFull = $this->getVar('gallery_image_url_full', false);
			if ($this->_galleryImageURLFull == 'false') $this->_galleryImageURLFull = false;
		}
		return $this->_galleryImageURLFull;
	}
		
	/**
	 * returns the number of the category level, which should be exported first at the category path
	 */
	private function _getFirstCategoryLevel()
	{
		if (!$this->_firstCategoryLevel) {
			$this->_firstCategoryLevel = intval($this->getVar('category_first_level', 1));
		}
		return $this->_firstCategoryLevel;
	}
	
	/**
	 * returns the category path with names
	 */
	private function _getCategoryPath($categoryId)
	{
		$categoryId = (string)$categoryId;
		
		if (!isset($this->_categoryPathCache[$categoryId])) {
			$cpd = $this->_getCategoryPathDelimiter();
			$firstLevel = $this->_getFirstCategoryLevel();
			
			$categoryPath = '';
			$category = Mage::getModel('catalog/category')->load($categoryId);
			
			if ($category->getIsActive() != 1) {
                //if is not active, do nothing => add empty string as "path"
            } else if ($firstLevel == -1) {
				//if category_first_level is -1, only export the category name
				$categoryPath = $category->getName();
			} else {
				//if first_category_level is not -1, export path, starting from 'path_category_level'
				while ($category->getParentId() != 0 && $category->getLevel() >= $firstLevel) {
					if ($categoryPath != '') {
						$categoryPath = $cpd . $categoryPath;
					}
					$categoryPath = $category->getName() . $categoryPath;
					$category = $category->getParentCategory();
				}
			}
			
			$this->_categoryPathCache[$categoryId] = $categoryPath;
		}
		
		return $this->_categoryPathCache[$categoryId];
	}
}
