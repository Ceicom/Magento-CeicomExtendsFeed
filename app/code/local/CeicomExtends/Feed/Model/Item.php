<?php
class CeicomExtends_Feed_Model_Item extends Ranvi_Feed_Model_Item
{

    public function writeTempFile($start, $length, $filename = '')
    {
        try {
            $filePath = $this->getTempFilePath($start);
            $fileDir = sprintf('%s/productsfeed', Mage::getBaseDir('media'));
            $hasRewriteEnabled = Mage::getStoreConfig('web/seo/use_rewrites', $this->getStoreId());
            $baseUrl = $this->getBaseUrl($hasRewriteEnabled);

            if (!file_exists($fileDir)) {
                mkdir($fileDir);
                chmod($fileDir, 0777);
            }

            if (is_dir($fileDir)) {

                switch ($this->getDelimiter()) {
                    case('comma'):
                    default:
                        $delimiter = ",";
                        break;
                    case('tab'):
                        $delimiter = "\t";
                        break;
                    case('colon'):
                        $delimiter = ":";
                        break;
                    case('space'):
                        $delimiter = " ";
                        break;
                    case('vertical pipe'):
                        $delimiter = "|";
                        break;
                    case('semi-colon'):
                        $delimiter = ";";
                        break;
                }

                switch ($this->getEnclosure()) {
                    case(1):
                    default:
                        $enclosure = "'";
                        break;
                    case(2):
                        $enclosure = '"';
                        break;
                    case(3):
                        $enclosure = ' ';
                        break;
                }

                $collection = $this->getProductsCollection();
                $collection->getSelect()->limit($length, $start);
                $maping = json_decode($this->getContent());
                $fp = fopen($filePath, 'w');
                $codes = array();

                foreach ($maping as $col) {
                    $codes[] = $col->attribute_value;
                }

                $attributes = Mage::getModel('eav/entity_attribute')
                    ->getCollection()
                    ->setEntityTypeFilter(Mage::getResourceModel('catalog/product')->getEntityType()->getData('entity_type_id'))
                    ->setCodeFilter($codes);

                $log_fp = fopen(sprintf('%s/productsfeed/%s', Mage::getBaseDir('media'), 'log-' . $this->getId() . '.txt'), 'a');

                fwrite($log_fp, date("F j, Y, g:i:s a") . ', page:' . $start . ', items selected:' . count($collection) . "\n");
                fclose($log_fp);

                $store = Mage::getModel('core/store')->load($this->getStoreId());
                $root_category = Mage::getModel('catalog/category')->load($this->getRootCategoryFeed());

                if (Mage::getStoreConfig('ranvi_feedpro/imagesettings/enable')) {
                    $image_width = intval(Mage::getStoreConfig('ranvi_feedpro/imagesettings/width'));
                    $image_height = intval(Mage::getStoreConfig('ranvi_feedpro/imagesettings/height'));
                } else {
                    $image_width = 0;
                    $image_height = 0;
                }

                foreach ($collection as $product) {
                    $fields = array();
                    $category = null;

                    foreach ($product->getCategoryIds() as $id) {
                        $_category = $this->getCategoriesCollection()->getItemById($id);
                        if (is_null($category) || ($category && $_category && $category->getLevel() < $_category->getLevel())) {
                            $category = $_category;
                        }
                    }

                    if ($category) {
                        $category_path = array($category->getName());
                        $parent_id = $category->getParentId();
                        if ($category->getLevel() > $root_category->getLevel()) {
                            while ($_category = $this->getCategoriesCollection()->getItemById($parent_id)) {
                                if ($_category->getLevel() <= $root_category->getLevel()) {
                                    break;
                                }
                                $category_path[] = $_category->getName();
                                $parent_id = $_category->getParentId();
                            }
                        }
                        $product->setCategory($category->getName());
                        $product->setCategoryId($category->getEntityId());
                        $product->setCategorySubcategory(implode(' > ', array_reverse($category_path)));
                    } else {
                        $product->setCategory('');
                        $product->setCategorySubcategory('');
                    }

                    $parent_product = $this->getParentProduct($product, $collection);
                    $_prod = Mage::getModel('catalog/product')->load($product->getId());

                    foreach ($maping as $col) {
                        $value = null;

                        if ($col->attribute_value) {
                            switch ($col->attribute_value) {
                                case ('parent_sku'):
                                    if ($parent_product && $parent_product->getEntityId()) {
                                        $value = $parent_product->getSku();
                                    } else {
                                        $value = '';
                                    }
                                    break;
                                case ('price'):
                                    if (in_array($product->getTypeId(), array(Mage_Catalog_Model_Product_Type::TYPE_GROUPED, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)))
                                        $value = $store->convertPrice($product->getMinimalPrice(), false, false);
                                    else
                                        $value = $store->convertPrice($product->getPrice(), false, false);
                                    break;
                                case ('store_price'):
                                    $value = $store->convertPrice($product->getFinalPrice(), false, false);
                                    break;
                                case ('parent_url'):
                                    if ($parent_product && $parent_product->getEntityId()) {
                                        $value = $this->getProductUrl($parent_product, $baseUrl);
                                    } else {
                                        $value = $this->getProductUrl($product, $baseUrl);
                                    }
                                    break;
                                case 'parent_base_image':
                                    if ($parent_product && $parent_product->getEntityId() > 0) {
                                        $_prod = Mage::getModel('catalog/product')->load($parent_product->getId());
                                    }

                                    try {
                                        if ($image_width || $image_height) {
                                            $image_url = (string)Mage::helper('catalog/image')->init($_prod, 'image')->resize($image_width, $image_height);
                                        } else {
                                            $image_url = (string)Mage::helper('catalog/image')->init($_prod, 'image');
                                        }
                                    } catch (Exception $e) {
                                        $image_url = '';
                                    }

                                    $value = $image_url;
                                    break;
                                case 'parent_description':
                                    $description = '';
                                    if ($parent_product && $parent_product->getEntityId() > 0) {
                                        $_prod = Mage::getModel('catalog/product')->load($parent_product->getId());
                                    }

                                    try {
                                        $description = $_prod->getDescription();
                                    } catch (Exception $e) {
                                        $description = '';
                                    }

                                    $value = $description;
                                    break;
                                case 'parent_product_price':
                                    if ($parent_product && $parent_product->getEntityId() > 0) {
                                        $_prod = Mage::getModel('catalog/product')->load($parent_product->getId());
                                    }

                                    try {
                                        $price = $_prod->getResource()->getAttribute('price')->getFrontend()->getValue($_prod);
                                    } catch (Exception $e) {
                                        $price = '';
                                    }

                                    $value = number_format($price);
                                    break;
                                case 'parent_product_special_price':
                                    if ($parent_product && $parent_product->getEntityId() > 0) {
                                        $_prod = Mage::getModel('catalog/product')->load($parent_product->getId());
                                    }

                                    try {
                                        $specialprice = $_prod->getResource()->getAttribute('special_price')->getFrontend()->getValue($_prod);
                                    } catch (Exception $e) {
                                        $specialprice = '';
                                    }

                                    $value = number_format($specialprice);
                                    break;
                                case 'parent_brand':
                                    $brand = '';
                                    if ($parent_product && $parent_product->getEntityId() > 0) {
                                            $_prod = Mage::getModel('catalog/product')->load($parent_product->getId());
                                        try {
                                            $brandAttr = $_prod->getResource()->getAttribute('brand');
                                            if ($brandAttr){
                                                $brand = $brandAttr->getFrontend()->getValue($_prod);
                                            }
                                        } catch (Exception $e) {
                                            $brand = '';
                                        }
                                    }

                                    $value = $brand;
                                    break;
                                case 'image_link':
                                    $url = Mage::getBaseUrl('media') . "catalog/product" . $_prod->getImage();
                                    if (!$_prod->getImage()) {
                                        if ($parent_product && $parent_product->getEntityId() > 0) {
                                            $_prod = Mage::getModel('catalog/product')->load($parent_product->getId());
                                            $url = Mage::getBaseUrl('media') . "catalog/product" . $_prod->getImage();
                                        }
                                    } else {
                                        $url = Mage::getBaseUrl('media') . "catalog/product" . $_prod->getImage();
                                    }

                                    if ($url == Mage::getBaseUrl('media') . "catalog/product" || $url == Mage::getBaseUrl('media') . "catalog/productno_selection") {
                                        $url = Mage::getBaseUrl('media') . "catalog/product/i/m/img-na-450_1.jpg";
                                    }

                                    $value = $url;
                                    break;
                                case 'parent_name':
                                    if ($parent_product && $parent_product->getEntityId() > 0) {
                                        $_prod = Mage::getModel('catalog/product')->load($parent_product->getId());
                                        $name = $_prod->getName();
                                    } else {
                                        $name = '';
                                    }
                                    $value = $name;
                                    break;
                                case('image'):
                                case('gallery'):
                                case('media_gallery'):
                                    if (!$product->hasData('product_base_image')) {
                                        try {
                                            if ($image_width || $image_height) {
                                                $image_url = (string)Mage::helper('catalog/image')->init($_prod, 'image')->resize($image_width, $image_height);
                                            } else {
                                                $image_url = (string)Mage::helper('catalog/image')->init($_prod, 'image');
                                            }
                                        } catch (Exception $e) {
                                            $image_url = '';
                                        }

                                        $product->setData('product_base_image', $image_url);
                                        $value = $image_url;
                                    } else {
                                        $value = $product->getData('product_base_image');
                                    }
                                    break;
                                case('image_2'):
                                case('image_3'):
                                case('image_4'):
                                case('image_5'):
                                    $i = 0;
                                    
                                    if (!$product->hasData('media_gallery_images')) {
                                        $product->setData('media_gallery_images', $_prod->getMediaGalleryImages());
                                    }
                                    
                                    foreach ($product->getMediaGalleryImages() as $_image) {
                                        $i++;

                                        if (('image_' . $i) == $col->attribute_value) {
                                            if ($image_width || $image_height) {
                                                $value = (string)Mage::helper('catalog/image')->init($product, 'image', $_image->getFile())
                                                    ->resize($image_width, $image_height);
                                            } else {
                                                $value = $_image['url'];
                                            }
                                        }
                                    }
                                    break;
                                case('url'):
                                    $value = $this->getProductUrl($product, $baseUrl);
                                    break;
                                case('qty'):
                                    $value = ceil((int)Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty());
                                    break;
                                case('category'):
                                    $value = $product->getCategory();
                                    break;
                                case ('product_type'):
                                    $value = $product->getTypeId();
                                    break;
                                case('is_in_stock'):
                                    $value = $product->getData('is_in_stock');
                                    break;
                                default:
                                    if ($attribute = $attributes->getItemByColumnValue('attribute_code', $col->attribute_value)) {
                                        if ($attribute->getFrontendInput() == 'select' || $attribute->getFrontendInput() == 'multiselect') {
                                            $value = implode(', ', (array)$product->getAttributeText($col->attribute_value));
                                        } else {
                                            $value = $product->getData($col->attribute_value);
                                        }
                                    } else {
                                        $value = $product->getData($col->attribute_value);
                                    }
                                    break;
                            }
                        } else {
                            $value = '';
                        }
                        
                        $fields[] = $value;
                    }

                    if ($enclosure != ' ') {
                        fputcsv($fp, $fields, $delimiter, $enclosure);
                    } else {
                        $this->myfputcsv($fp, $fields, $delimiter);
                    }

                    if ($product->getTypeId() == 'simple') {
                        $product->clearInstance();
                    }
                }

                fclose($fp);

                foreach ($this->_parentProductsCache as $one_cache_key => $one_cache_val) {
                    if ($one_cache_val != null && $one_cache_val instanceof Mage_Core_Model_Abstract) {
                        $one_cache_val->clearInstance();
                    }

                    unset($this->_parentProductsCache[$one_cache_key]);
                    unset($one_cache_val);
                }

                $this->_parentProductsCache = array();
                $collection->clear();
                unset($collection);
                gc_collect_cycles();

            }
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

}