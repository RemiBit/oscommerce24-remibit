<?php
/*
Modified April 15th 2020 by Blockchain Remittance Ltd.
Adapted to handle calls to RemiBit API.
*/

/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2008 osCommerce

  Released under the GNU General Public License
*/

use OSC\OM\OSCOM;

chdir('../../../../');
    require('includes/application_top.php');
    include('includes/modules/payment/remibit.php');

     $url = OSCOM::link('checkout_process.php');
     $remibit = new remibit();
     $post_string = [];
     foreach ($_POST as $key => $value) {
	$post_string[] = "<input type='hidden' name='$key' value='$value'/>";
     }
     $remibit->sendTransactionToGateway($url, $post_string);

    
  require('includes/application_bottom.php');
?>
