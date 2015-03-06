<?php

class CeicomExtends_Feed_Block_Adminhtml_Items_Edit_Tab_Main extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        
        if (Mage::registry('ranvi_feed')) {
            $item = Mage::registry('ranvi_feed');
        } else {
            $item = new Varien_Object();
        }

        $this->setForm($form); 

        $fieldset = $form->addFieldset('main_fieldset', array(
            'legend' => $this->__('Item information')
        ));

        $fieldset->addField('type', 'hidden', array(
            'name'      => 'type',
        ));

        $fieldset->addField('name', 'text', array(
            'name'      => 'name',
            'label'     => $this->__('Name'),
            'title'     => $this->__('Name'),
            'required'  => true,
        ));

        if ($item->getId() && ($url = $item->getUrl())) {
            $fieldset->addField('comments', 'note', array(
                'label'    => $this->__('Access Url'),
                'title'    => $this->__('Access Url'),
                'text'    => '<a href="'.$url.'" target="_blank">'.$url.'</a>'
            ));
        }

        $fieldset->addField('filename', 'text', array(
            'name'      => 'filename',
            'label'     => $this->__('Filename'),
            'title'     => $this->__('Filename'),
            'required'  => false
        ));

        $fieldset->addField('store_id', 'select', array(
            'label'     => $this->__('Store View'),
            'required'  => true,
            'name'      => 'store_id',
            'values'    => Mage::getModel('ranvi_feed/adminhtml_system_config_source_store')->getStoreValuesForForm()
        ));

        //Get the root categories
        $categories = Mage::getModel('catalog/category')->getCollection()->addAttributeToFilter('level', 1);
        $options = [];

        foreach ($categories as $category) {
            $categoryData = Mage::getModel('catalog/category')->load($category->getEntityId());
            $options[] = array('label' => $categoryData->getName(), 'value' => $categoryData->getId());
        }

        $fieldset->addField('root_category_feed', 'select', array(
            'label'     => $this->__('Root Category'),
            'required'  => true,
            'name'      => 'root_category_feed',
            'values'    => $options
        ));

        if (!$item->getType() && $this->getRequest()->getParam('type')) {
            $item->setType($this->getRequest()->getParam('type'));
        }

        $form->setValues($item->getData());

        return parent::_prepareForm();
    }

}