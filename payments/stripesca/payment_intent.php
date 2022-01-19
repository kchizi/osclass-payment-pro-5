<?php if ( ! defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');

ob_get_clean();

$stripe = StripeSCAPayment::getEnvironment();

\Stripe\Stripe::setApiKey($stripe['secret_key']);

// get id and parameters
$params = Params::getParam('data');
if (!empty($params)) {
    $params = urldecode($params);
    $params = json_decode($params, true);
}

$user = User::newInstance()->findByIdSecret($params['userid'],$params['secret']);
if ($user == false) {
    echo json_encode(["status"=>"fail","error"=>"Invalid params"]);
    http_response_code(200); // PHP 5.4 or greater
    exit();
}

$id = Params::getParam('id');
$amount = $params['amount'];
$currency = $params['currency'];
$metadata = array('user'=> $params['userid'],
                'email' => $user['s_email'],
                'items' => $params['items'],
                'details' => $params['details'],
                'amount_total' => $amount / 100,
    );
try {
    if(empty($id) || $id == 'pi') {
      $pi = \Stripe\PaymentIntent::create([
        "amount" => $amount,
        "currency" => $currency,
        "setup_future_usage" => "off_session",
        "metadata" => $metadata,
      ]);
      // return some basic data
      echo json_encode(["status"=>"ok","id"=>$pi->id,"client_secret"=>$pi->client_secret,"amount"=>$pi->amount,"currency"=>$pi->currency]);
    } else {
        $action = Params::getParam('action');
        $pi = \Stripe\PaymentIntent::retrieve(["id"=>$id]);
        if ($action == "delete") {
            $pi->cancel();
            echo json_encode(["status"=>"ok","id"=>$pi->id,"amount"=>$pi->amount,"currency"=>$pi->currency]);        
        } else {
            // or update amount of the existing intent
            $pi->amount = $amount;
            $pi->metadata = $metadata;
            $pi->save();
            echo json_encode(["status"=>"ok","id"=>$pi->id,"amount"=>$pi->amount,"currency"=>$pi->currency]);
        }
    }
} catch (Exception $e) {
    echo json_encode(["status"=>"fail","error"=>'Intent processing error: ' . $e->getMessage()]);
    http_response_code(500); // PHP 5.4 or greater
    exit();
}
http_response_code(200); // PHP 5.4 or greater
exit();
