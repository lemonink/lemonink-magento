<?php
error_reporting(E_ALL);
require_once  __DIR__ . "/../vendor/autoload.php";

class LemonInk_LemonInk_Model_Observer
{
    private $_client;
    
    public function createTransactions($observer)
    {
        
        $order = $observer->getEvent()->getOrder();

        if (!$order->getId()) {
            //order not saved in the database
            return $this;
        }
        
        $itemIds = [];
        foreach ($order->getAllItems() as $item) {
            $itemIds[] = $item->getId();
        }

        $links = Mage::getResourceModel('downloadable/link_purchased_item_collection')
            ->addFieldToFilter('status', Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE)
            ->addFieldToFilter('order_item_id', array('in' => $itemIds));

        $linksToWatermark = array();

        foreach ($links as $link) {
            $url = parse_url($link->getLinkUrl());
            if ($url['scheme'] == 'lemonink') {
                $parts = explode('.', ltrim($url['path'], '/'));
                $masterId = $parts[0];
                $extension = $parts[1];
                if (!isset($linksToWatermark[$masterId])) {
                    $linksToWatermark[$masterId] = array();
                }
                $linksToWatermark[$masterId][$extension] = $link;
            }
        }

        foreach ($linksToWatermark as $masterId => $links) {
            $transaction = new LemonInk\Models\Transaction();
            $transaction->setMasterId($masterId);
            $transaction->setWatermarkValue($order->getCustomerEmail());
 
            $this->getClient()->save($transaction);

            foreach ($links as $extension => $link) {
                $link->setLinkUrl($transaction->getUrl($extension))
                ->save();
            }
        }
    }

    private function getClient()
    {
        if (!isset($this->_client)) {
            $this->_client = new LemonInk\Client(Mage::getStoreConfig('lemonink_configuration/api/api_key'));
        }
        return $this->_client;
    }
}