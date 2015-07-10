<?php
/**
 * this class adds functions to modify the export data, particularly in respect to Magmi 
 *
 * @copyright  Copyright (c) 2009 Omikron Data Quality GmbH (http://www.omikron.net)
 * @copyright  Copyright (c) 2013 Richard Aspden (http://www.insanityinside.net)
 * @author     Rudolf Batt (rb@omikron.net)
 * @author     Richard Aspden (magento@insanityinside.net)
 * @version    1.4
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
	private $_addConfigurableAttributes;
	private $_addConfigurableAttributesPricingLine;
	private $_productImageBaseURL;
	private $_upsellFieldName;
	private $_upsellDelimiter;
	private $_crossSellFieldName;
	private $_crossSellDelimiter;
	private $_relatedProductsFieldName;
	private $_relatedProductsDelimiter;
	
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
	 * adds group_price to the row
	 * format: 
	 * table_name: group_price:groupName
	 * field: 15.00
	 */
	protected function _addGroupPrice(&$row, &$product)
	{
		$groupPriceFieldName = 'group_price:';
		
		//get all possible field names for product
		
		$group_price = $product->getData('group_price');
		
		
		if (!is_null($group_price) || is_array($group_price)) {
			foreach ($group_price as $group) {
				var_dump($group);
			}
		}
		
		die();
		
		
		
		
		
		
		Mage::getModel('customer/group')->getCollection();
		
		
		$categoryDelimiter = $this->_getCategoryDelimiter();
	
		$row[$groupPriceFieldName] = '';
		
		$tempCatPath = '';
		
		foreach($product->getCategoryIds() as $categoryId){
			$tempCatPath = $this->_getCategoryPath($categoryId);
			if ($tempCatPath != '') {
				//dont add delimiter if previous category path was empty or no category was added yet
				if( $tempCatPath != '' && $row[$categoryFieldName] != '') {
					$row[$categoryFieldName] .= $categoryDelimiter;
				}
				$row[$categoryFieldName] .= $tempCatPath;
			}
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
            $tempCatPath = $this->_getCategoryPath($categoryId);
			if ($tempCatPath != '') {
            //dont add delimiter if previous category path was empty or no category was added yet
				if( $tempCatPath != '' && $row[$categoryFieldName] != '') {
					$row[$categoryFieldName] .= $categoryDelimiter;
				}
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

 		// Check to see if configurable
		if ($product->getTypeId() == "configurable") {
			$_product = Mage::getModel('catalog/product')->load($product->getId());
			$attributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($_product);
			$childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$_product);
			$childSkuArray = array();
			if ($this->_addConfigurableAttributes ) {
				$childAttributeArray = array();
				$configurableAttributesFieldName = $this->_getConfigurableAttributesFieldName();
				$configurableAttributesDelimiter = $this->_getConfigurableAttributesDelimiter();
				$configurableAttributesPricingLine = $this->_getAddConfigurableAttributesPricingLine();
				$attributeArray = array();
			}
			foreach($childProducts AS $childProduct) {
				$childSkuArray[] = $childProduct->getSku();
				if ($this->_addConfigurableAttributes ) {
					$attributeSubArray = array();
					// Test attribute code
					foreach ($attributes as $attribute) {
					    foreach ($attribute['values'] as $value){
					        $childValue = $childProduct->getData($attribute['attribute_code']);
					        if ($value['value_index'] == $childValue){
					            $attributeSubArray[$attribute['store_label']] = $value['store_label'];
					            // if (!in_array($attribute['store_label'],$attributeArray)) {
					            	// $attributeArray[] = $attribute['store_label'];
					            if (!in_array($attribute['attribute_code'],$attributeArray)) {
					            	$attributeArray[] = $attribute['attribute_code'];
					            }
					        }
					    }
					}
					$childAttributeArray[$childProduct->getSku()] = $attributeSubArray;
					// End test attribute code
				}
			}
			if ($childSkuFieldName != false) {
				$row[$childSkuFieldName] = implode($childSkuDelimiter,$childSkuArray);
			}
			if ($this->_addConfigurableAttributes ) {
				$row[$configurableAttributesFieldName] = implode($configurableAttributesDelimiter,$attributeArray);
			}
		}
	}
	
/*	protected function _addConfigurableAttributes(&$row, &$product)
	{
		$configurableAttributesFieldName = $this->_getConfigurableAttributesFieldName();
		$configurableAttributesDelimiter = $this->_getConfigurableAttributesDelimiter();

		// See if a child product
		list($parentID) = Mage::getResourceModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
		if ($parentID != null) {
			// Do something to get the attributes
			/*$temp = array();
			//$test = Mage::getModel('catalog/product')->load($product->getId());
			$_attributes = $product->getTypeInstance(true)->getConfigurableAttributes($product);
		    foreach($_attributes as $key => $_attribute){
		        $temp[$key] = $_attribute;
		    }
			$row[$configurableAttributesFieldName] = print_r($temp,1); 
		} else {
			$_product = Mage::getModel("catalog/Product")->load($product->getId());
			$_attributes = Mage::getModel('catalog/product_type_configurable')->getConfigurableAttributes($_product);
		    /*foreach($_attributes as $key => $_attribute){
		        $temp[$key] = $_attribute;
		    }
			$row[$configurableAttributesFieldName] = print_r($_attributes,1);
		}
	} */

	protected function _addGalleryImageURLs(&$row, &$product)
	{
		$galleryImageURLFieldName = $this->_getGalleryImageURLFieldName();
		$galleryImagesDelimiter = $this->_getGalleryImageURLDelimiter();
		$galleryImageURLFull = $this->_getGalleryImageURLFull();
		
		$mediaGallery = $product->getMediaGallery();
		$mediaGallery = $mediaGallery['images'];
		$galleryImageURLs = array();
		$_baseurl = $this->_getProductImageBaseURL();

		
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

	protected function _addUrlToImageFields(&$row, &$product) {
		if (trim($row['image']) != '') $row['image'] = $this->_calculateProductImageURL($row['image']);
		if (trim($row['small_image']) != '') $row['small_image'] = $this->_calculateProductImageURL($row['small_image']);
		if (trim($row['thumbnail']) != '') $row['thumbnail'] = $this->_calculateProductImageURL($row['thumbnail']);
	}

	protected function _addUpsellSkus(&$row, &$product) {
		$upsellProductSkuArray = array();
		$upsellProducts = Mage::getModel('catalog/product')->load($product->getId())->getUpsellProducts();
		foreach ($upsellProducts as $upsellProduct) {
			$upsellProductSkuArray[] = $upsellProduct->getSku();
		}
		$row[$this->_upsellFieldName] = implode($this->_upsellDelimiter,$upsellProductSkuArray);
	}

	protected function _addCrossSellSkus(&$row, &$product) {
		$crossSellProductSkuArray = array();
		$crossSellProducts = Mage::getModel('catalog/product')->load($product->getId())->getCrossSellProducts();
		foreach ($crossSellProducts as $crossSellProduct) {
			$crossSellProductSkuArray[] = $crossSellProduct->getSku();
		}
		$row[$this->_crossSellFieldName] = implode($this->_crossSellDelimiter,$crossSellProductSkuArray);
	}

	protected function _addRelatedProductsSkus(&$row, &$product) {
		$relatedProductskuArray = array();
		$relatedProducts = Mage::getModel('catalog/product')->load($product->getId())->getRelatedProducts();
		foreach ($relatedProducts as $relatedProduct) {
			$relatedProductskuArray[] = $relatedProduct->getSku();
		}
		$row[$this->_relatedProductsFieldName] = implode($this->_crossSellDelimiter,$relatedProductskuArray);
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
			$this->_addConfigurableAttributes = false;
		} else {
			$this->_configurableAttributesFieldName = $addConfigurableAttributes;
			$this->_addConfigurableAttributes = true;
		}

		$addGalleryImageURLs = $this->getVar('add_gallery_image_urls', '');
		if (empty($addGalleryImageURLs)) {
			$addGalleryImageURLs = false;
		}

		$addProductImageURL = $this->getVar('add_product_image_url', false);
		if (empty($addProductImageURL) || $addProductImageURL == 'false') {
			$addProductImageURL = false;
		} else {
			$addProductImageURL = true;
		}

		$addUpsell = $this->getVar('add_upsell', false);
		if (empty($addUpsell) || $addUpsell == 'false') {
			$addUpsell = false;
		} else {
			$this->_upsellFieldName = $addUpsell;
			$this->_upsellDelimiter = $this->getVar('upsell_delimiter', ',');
			$addUpsell = true;
		}

		$addCrossSell = $this->getVar('add_cross_sell', false);
		if (empty($addCrossSell) || $addCrossSell == 'false') {
			$addCrossSell = false;
		} else {
			$this->_crossSellFieldName = $addCrossSell;
			$this->_crossSellDelimiter = $this->getVar('cross_sell_delimiter', ',');
			$addCrossSell = true;
		}

		$addRelatedProducts = $this->getVar('add_rp', false);
		if (empty($addRelatedProducts) || $addRelatedProducts == 'false') {
			$addRelatedProducts = false;
		} else {
			$this->_relatedProductsFieldName = $addRelatedProducts;
			$this->_relatedProductsDelimiter = $this->getVar('related_products_delimiter', ',');
			$addRelatedProducts = true;
		}


		if (!$addCategories && !$removeLineBreaks && !$removeHtmlTags && !$addParentSku && !$addChildSku && !$addGalleryImageURLs && !$addConfigurableAttributes && !$addProductImageURL && !$addUpsell && !$addCrossSell && !$addRelatedProducts) {
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
			
			
			$this->_addGroupPrice($row, $product);
			
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

			if ($addChildSku !== false || $addConfigurableAttributes !== false) {
				$this->_addChildSkus($row, $product);
			}
			
			if ($addGalleryImageURLs !== false) {
				$this->_addGalleryImageURLs($row, $product);
			}

			if ($addProductImageURL !== false) {
				$this->_addUrlToImageFields($row,$product);
			}

			if ($addUpsell !== false) {
				$this->_addUpsellSkus($row,$product);
			}

			if ($addCrossSell !== false) {
				$this->_addCrossSellSkus($row,$product);
			}

			if ($addRelatedProducts !== false) {
				$this->_addRelatedProductsSkus($row,$product);
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
			$this->_categoryDelimiter = $this->getVar('category_delimiter', ';;');
		}
		return $this->_categoryDelimiter;
	}
	
	/**
	 * returns the category path delimiter used at the export
	 */
	private function _getCategoryPathDelimiter()
	{
		if (!$this->_categoryPathDelimiter) {
			$this->_categoryPathDelimiter = $this->getVar('category_path_delimiter', '/');
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
			$this->_childSkuFieldName = $this->getVar('add_child_sku', false);
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

	private function _getAddConfigurableAttributesPricingLine()
	{
		if (!$this->_addConfigurableAttributesPricingLine) {
			$this->_addConfigurableAttributesPricingLine = $this->getVar('add_configurable_attributes_pricing_line', false);
		}
		return $this->_addConfigurableAttributesPricingLine;
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

	private function _getProductImageBaseURL() {
		if (!$this->_productImageBaseURL) {
			$this->_productImageBaseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/';
		}
		return $this->_productImageBaseURL;
	}
	
	protected function _calculateProductImageURL($imagefield) {
		$_baseURL = $this->_getProductImageBaseURL();
		if (substr($_baseURL,-1) == '/' && substr($imagefield,0,1) == '/') { // Clean up double slashes
			return $_baseURL.str_replace('//','/',substr($imagefield,1));
		} else {
			return $_baseURL.str_replace('//','/',$imagefield);
		}
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
