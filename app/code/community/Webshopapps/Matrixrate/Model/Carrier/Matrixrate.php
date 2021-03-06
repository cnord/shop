<?php
/**
 * Webshopapps Shipping Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * Shipping MatrixRates
 *
 * @category   Webshopapps
 * @package    Webshopapps_Matrixrate
 * @copyright  Copyright (c) 2010 Zowta Ltd (http://www.webshopapps.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Karen Baker <sales@webshopapps.com>
*/

class Webshopapps_Matrixrate_Model_Carrier_Matrixrate
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    protected $_code = 'matrixrate';
    protected $_default_condition_name = 'package_weight';

    protected $_conditionNames = array();

    public function __construct()
    {
        parent::__construct();
        foreach ($this->getCode('condition_name') as $k=>$v) {
            $this->_conditionNames[] = $k;
        }
    }

    /**
     * Enter description here...
     *
     * @param Mage_Shipping_Model_Rate_Request $data
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $freeBoxes = 0;
        $found=false;
        $virtualTotal=0;
        foreach ($request->getAllItems() as $item) {
        	if ($item->getFreeShipping() && $item->getProductType()!= Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL
        		&& $item->getProductType() != 'downloadable') {
                    $freeBoxes+=$item->getQty();
            }
            if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL ||
                 $item->getProductType() == 'downloadable') {
                    $virtualTotal+= $item->getBaseRowTotal();
                    $found=true;
            } 
        }
        if ($found && $this->getConfigFlag('remove_virtual')) {
        	$request->setPackageValue($request->getPackageValue()-$virtualTotal);
        }
        $this->setFreeBoxes($freeBoxes);

        $request->setConditionName($this->getConfigData('condition_name') ? $this->getConfigData('condition_name') : $this->_default_condition_name);

        $result = Mage::getModel('shipping/rate_result');
     	$ratearray = $this->getRate($request);

     	$freeShipping=false;
     	
     	if ($this->getConfigData('enable_free_shipping_threshold') && 
	        $this->getConfigData('free_shipping_threshold')!='' && 
	        $this->getConfigData('free_shipping_threshold')>0 &&
	        $request->getPackageValue()>$this->getConfigData('free_shipping_threshold')) {
	         	$freeShipping=true;
	    }
    	if ($this->getConfigData('allow_free_shipping_promotions') &&
	        ($request->getFreeShipping() === true || 
	        $request->getPackageQty() == $this->getFreeBoxes()))
        {
         	$freeShipping=true;
        }
        if ($freeShipping)
        {
		  	$method = Mage::getModel('shipping/rate_result_method');
			$method->setCarrier('matrixrate');
			$method->setCarrierTitle($this->getConfigData('title'));
			$method->setMethod('matrixrate_free');
			$method->setPrice('0.00');
			$method->setMethodTitle($this->getConfigData('free_method_text'));
			$result->append($method);
			
			if ($this->getConfigData('show_only_free')) {
				return $result;
			}
		}
     	
	   foreach ($ratearray as $rate)
		{
		   if (!empty($rate) && $rate['price'] >= 0) {
			  $method = Mage::getModel('shipping/rate_result_method');

				$method->setCarrier('matrixrate');
				$method->setCarrierTitle($this->getConfigData('title'));

				$method->setMethod('matrixrate_'.$rate['pk']);

				$method->setMethodTitle(Mage::helper('matrixrate')->__($rate['delivery_type']));

				$shippingPrice = $this->getFinalPriceWithHandlingFee($rate['price']);
				$method->setCost($rate['cost']);
				$method->setDeliveryType($rate['delivery_type']);
        		
				$method->setPrice($shippingPrice);

				$result->append($method);
			}
		}

		// ITEAMO . DELIN DELIVERY CALCULATOR <<<       
      require_once($_SERVER['DOCUMENT_ROOT'] . '/iteamoDelinDeliveryCalculator/iteamoDelinDeliveryCalculator.php');
      $iteamoDelinDeliveryCalculator = new iteamoDelinDeliveryCalculator();
      iteamoDelinDeliveryCalculator::log($result, '$result', $file = __FILE__);  
    // >>> ITEAMO . DELIN DELIVERY CALCULATOR
                        
        return $result;
    }

    public function getRate(Mage_Shipping_Model_Rate_Request $request)
    {
        return Mage::getResourceModel('matrixrate_shipping/carrier_matrixrate')->getNewRate($request,$this->getConfigFlag('zip_range'));
    }

    public function getCode($type, $code='')
    {
        $codes = array(

            'condition_name'=>array(
                'package_weight' => Mage::helper('shipping')->__('Weight vs. Destination'),
                'package_value'  => Mage::helper('shipping')->__('Price vs. Destination'),
                'package_qty'    => Mage::helper('shipping')->__('# of Items vs. Destination'),
            ),

            'condition_name_short'=>array(
                'package_weight' => Mage::helper('shipping')->__('Weight (and above)'),
                'package_value'  => Mage::helper('shipping')->__('Order Subtotal (and above)'),
                'package_qty'    => Mage::helper('shipping')->__('# of Items (and above)'),
            ),

        );

        if (!isset($codes[$type])) {
            throw Mage::exception('Mage_Shipping', Mage::helper('shipping')->__('Invalid Matrix Rate code type: %s', $type));
        }

        if (''===$code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            throw Mage::exception('Mage_Shipping', Mage::helper('shipping')->__('Invalid Matrix Rate code for type %s: %s', $type, $code));
        }

        return $codes[$type][$code];
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array('matrixrate'=>$this->getConfigData('name'));
    }

}
