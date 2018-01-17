<?php
require 'api.class.php';
use WHMCS\Database\Capsule as DB;

/*
//GET WHMCS Version
global $CONFIG;
print_r(preg_replace("/[^0-9,.]/", "", $CONFIG['Version']));
*/

function synergywholesale_hosting_renameModule($names_map) {
    foreach ($names_map as $old=>$new) {
        foreach (DB::table('tblproducts')->where('servertype', $old)->select('id')->get() as $pr) {
            DB::table('tblproducts')
                ->where('id', $pr->id)
                ->update(['servertype' => $new]);
        }
    }
}

function synergywholesale_hosting_ConfigOptions() {
    synergywholesale_hosting_renameModule(
            ['synergyWholesaleHosting'=>'synergywholesale_hosting']
    );
    //Module Version Definition
    $version = "1.2.3";
    $relid = isset($_POST["id"]) ? $_POST["id"] : $_GET['id'];
    $data = DB::table('tblhosting')
        ->where('id', $relid)
        ->select('packageid')
        ->first();

    $productid = $data->packageid;

    # Should return an array of the module options for each product - maximum of 24
    DB::table('tblproducts')
        ->where('id', $relid)
        ->update(["configoption5" => $version]);

    $configarray = [
        "API Key" => ["Type" => "text", "Size" => "60"],
        "Reseller ID" => ["Type" => "text", "Size" => "60"],
        "Hosting Plan" => ["FriendlyName" => "Hosting Plan", "Type" => "dropdown", "Options" => "EW1,EW2,EW3,EW4,EW5,BW1,BW2,BW3,BW4,BW5", "Size" => "60"],
        "Hosting Location" => ["FriendlyName" => "Hosting Location", "Type" => "dropdown", "Options" => "NextDC S1 - Sydney Australia,NextDC M1 - Melbourne Australia", "Size" => "60"],
        "Module Version" => ["FriendlyName" => "Module Version", "Description" => $version],
        "IP Address" => ["FriendlyName" => "IP Address", "Description" => $_SERVER['SERVER_ADDR'] . ' - You\'ll need to provide IP address to access our API']
    ];

    $fields = [['name' => 'Hosting Id', 'type' => 'text'], ['name' => 'Server IP Address', 'type' => 'text']];

    foreach ($fields as $field) {
        if (!DB::table('tblcustomfields')->where([['relid', $relid], ['fieldname', $field['name']]])->count()) {
            $values = [
                "relid" => $relid,
                "type" => "product",
                "fieldname" => $field['name'],
                "fieldtype" => $field['type'],
                "fieldoptions" => "",
                "adminonly" => "on",
                "sortorder" => "0"
            ];

            DB::table('tblcustomfields')->insert($values);
        }
    }
    return $configarray;
}

function synergywholesale_hosting_AdminCustomButtonArray() {
    $buttonarray = array(
        "Recreate Service" => "recreate",
        "Synchronize Data" => "synchronize"
    );
    return $buttonarray;
}

function customValues($params) {
    $result = DB::table('tblcustomfields')
        ->select('id', 'fieldname')
        ->where('relid', $params["pid"])
        ->get();
    $ids = [];

    foreach ($result as $data) {
        $ids[$data->fieldname] = $data->id;
    }
    unset($result);

    $result = DB::table('tblcustomfieldsvalues')
        ->where('relid', $params['serviceid'])
        ->select('fieldid', 'relid', 'value')
        ->get();

    $values = [];
    foreach ($result as $data) {
        $values[$data->fieldid] = $data->value;
    }

    $customValues = array(
        "hoid" => $values[$ids['Hosting Id']],
        "server_ip" => $values[$ids['Server IP Address']],
        "ids" => $ids
    );
    return $customValues;
}

function synergywholesale_hosting_synchronize($params) {
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $customValues = customValues($params);
    $hoid = $customValues["hoid"];

    $updateCustomField = function($service_id, $field_id, $value) {
        $serviceCustomField = DB::table('tblcustomfieldsvalues')
            ->where("fieldid", $field_id)
            ->where("relid", $service_id)
            ->first();

        if ($serviceCustomField) {
            return DB::table('tblcustomfieldsvalues')
                ->where("fieldid", $field_id)
                ->where("relid", $service_id)
                ->update(['value' => $value]); 
        } else {
            return DB::table('tblcustomfieldsvalues')->insert([
                'fieldid' => $field_id, 
                "relid" => $service_id,
                "value" => $value
            ]);
        }
    };

    $data = [
        'hoid' => $hoid,
        'reason' => "WHMCS",
        "api_method" => "1",
        "whmcs_ver" => "7.2",
        "whmcs_mod_ver" => $params["configoption5"]
    ];

    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'hostingGetService', $data);

    if ($apiResult->errorMessage == "Hosting Get Service Completed Successfully") {
        $updateCustomField($params['serviceid'], $customValues["ids"]['Server IP Address'], $apiResult->serverIPAddress);
        DB::table('tblhosting')
                ->where('id', $params['serviceid'])
                ->update([
                    "username" => $apiResult->username,
                    "domain" => $apiResult->domain,
                    "dedicatedip" => $apiResult->dedicatedIPv4,
                    "password" => encrypt($apiResult->password)
        ]);
        return "success";
    } else {
        return $apiResult->errorMessage . ". Error code: " . $apiResult->status;
    }
}

function synergywholesale_hosting_CreateAccount($params) {
    $domain = $params["domain"];
    $email = $params['clientsdetails']["email"];
    $username = $params["username"];
    $password = $params["password"];
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $plan = $params["configoption3"];
    $location = $params["configoption4"];

    $customValues = customValues($params);

    $updateCustomField = function($service_id, $field_id, $value) {
        $serviceCustomField = DB::table('tblcustomfieldsvalues')
            ->where("fieldid", $field_id)
            ->where("relid", $service_id)
            ->first();

        if ($serviceCustomField) {
            return DB::table('tblcustomfieldsvalues')
                ->where("fieldid", $field_id)
                ->where("relid", $service_id)
                ->update(['value' => $value]); 
        } else {
            return DB::table('tblcustomfieldsvalues')->insert([
                'fieldid' => $field_id, 
                "relid" => $service_id,
                "value" => $value
            ]);
        }
    };

    if (strpos($location, 'Melbourne') !== false) {
        $customValues["locationName"] = "MELBOURNE";
    } else {
        $customValues["locationName"] = "SYDNEY";
    }
    $data = [
        "planName" => $plan,
        "locationName" => $customValues["locationName"],
        "domain" => $domain,
        "email" => $email,
        "username" => $username,
        "password" => $password,
        "api_method" => "WHMCS",
        "whmcs_ver" => "7.2",
        "whmcs_mod_ver" => $params["configoption5"]
    ];

    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'createAccount', $data);
    if (in_array($apiResult->status, ['OK', 'OK_PENDING'], true)) {
        $apiSyncResult = synergywholesale_hosting_api($resellerId, $apiKey, 'hostingGetService', [
            'hoid' => $apiResult->hoid,
            'reason' => "WHMCS",
            "api_method" => "1",
            "whmcs_ver" => "7.2"
        ]);
        $fieldsToUpdate = [['field_id' => $customValues["ids"]['Hosting Id'], 'value' => $apiResult->hoid]];

        if (isset($apiSyncResult->serverIPAddress))
            $fieldsToUpdate[] = [
                'field_id' => $customValues["ids"]['Server IP Address'], 
                'value' => $apiSyncResult->serverIPAddress
            ];

        foreach ($fieldsToUpdate as $customFields) {
            $updateCustomField($params["serviceid"], $customFields['field_id'], $customFields['value']);
        }
        if (isset($apiResult->username) && $apiResult->username != "") {
            DB::table('tblhosting')
                    ->where("id", $params["serviceid"])
                    ->update(['username' => $apiResult->username]);
        }
        return "success";
    } else {
        return $apiResult->errorMessage . ". Error code: " . $apiResult->status;
    }

}

function synergywholesale_hosting_recreate($params) {
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $customValues = customValues($params);
    $hoid = $customValues["hoid"];

    $data = array(
        'newPassword' => "AUTO",
        'hoid' => $hoid,
        'reason' => "WHMCS",
        "api_method" => "1",
        "whmcs_ver" => "7.2",
        "whmcs_mod_ver" => $params["configoption5"]
    );

    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'recreateAccount', $data);

    if ($apiResult->status == "OK") {
        DB::table('tblhosting')
                ->where("id", $params["serviceid"])
                ->update(['password'=>encrypt($apiResult->password)]);
    }
    return synergywholesale_hosting_status($apiResult);
}

function synergywholesale_hosting_TerminateAccount($params) {
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $customValues = customValues($params);
    $hoid = $customValues["hoid"];

    $data = array(
        'hoid' => $hoid,
        'reason' => "WHMCS",
        "api_method" => "1",
        "whmcs_ver" => "7.2",
        "whmcs_mod_ver" => $params["configoption5"]
    );

    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'terminateAccount', $data);

    return synergywholesale_hosting_status($apiResult);
}

function synergywholesale_hosting_SuspendAccount($params) {
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $customValues = customValues($params);
    $hoid = $customValues["hoid"];

    $data = array(
        'hoid' => $hoid,
        'reason' => "WHMCS",
        "api_method" => "1",
        "whmcs_ver" => "5.3",
        "whmcs_mod_ver" => $params["configoption5"]
    );

    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'suspendAccount', $data);

    return synergywholesale_hosting_status($apiResult);
}

function synergywholesale_hosting_UnsuspendAccount($params) {
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $customValues = customValues($params);
    $hoid = $customValues["hoid"];

    $data = array(
        'hoid' => $hoid,
        "api_method" => "1",
        "whmcs_ver" => "5.3",
        "whmcs_mod_ver" => $params["configoption5"]
    );
    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'unsuspendAccount', $data);

    return synergywholesale_hosting_status($apiResult);
}

function synergywholesale_hosting_ChangePassword($params) {

    $password = $params["password"];
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $customValues = customValues($params);
    $hoid = $customValues["hoid"];
    $data = array(
        "hoid" => $hoid,
        "newPassword" => $password,
        "api_method" => "1",
        "whmcs_ver" => "7.2",
        "whmcs_mod_ver" => $params["configoption5"]
    );

    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'changePass', $data);

    return synergywholesale_hosting_status($apiResult);
}

function synergywholesale_hosting_api($resellerId, $apiKey, $action, $data) {
    try {
        $api = new soapApi($resellerId, $apiKey);
        $result = $api->$action($data);
        logModuleCall('Synergy Hosting', $action, $data, (array)$result);
        return $result;

    } catch (\SoapFault $e) {
        logModuleCall('Synergy Hosting', $action, $data, ['exception' => 'SoapFault', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    } catch (\Exception $e) {
        logModuleCall('Synergy Hosting', $action, $data, ['exception' => 'Exception', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
    return null;
}

function synergywholesale_hosting_ChangePackage($params) {
    $resellerId = $params["configoption2"];
    $apiKey = $params["configoption1"];
    $plan = $params["configoption3"];
    $location = $params["configoption4"];
    $customValues = customValues($params);
    if (strpos($location, 'Melbourne') !== false) {
        $customValues["locationName"] = "MELBOURNE";
    } else {
        $customValues["locationName"] = "SYDNEY";
    }
    $data = [
        "newPlanName" => $plan,
        "newLocationName" => $customValues["locationName"],
        "hoid" => $customValues["hoid"],
        "api_method" => "1",
        "whmcs_ver" => "7.2",
        "whmcs_mod_ver" => $params["configoption5"]
    ];

    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'changePackage', $data);
    return synergywholesale_hosting_status($apiResult);
}

function synergywholesale_hosting_status($apiResult) {
    if (is_null($apiResult))
        return 'Fatal error';

    if ($apiResult->status == "OK") {
        return "success";
    } else {
        return $apiResult->errorMessage . ". Error code: " . $apiResult->status;
    }
}

function synergywholesale_hosting_ClientArea($params) {

    $resellerId = $params['templatevars']["moduleParams"]["configoption2"];
    $apiKey = $params['templatevars']["moduleParams"]["configoption1"];
    $customValues = customValues($params);

    $hoid = $customValues["hoid"];
    $data = [
        'hoid' => $hoid,
        'reason' => "WHMCS",
        "api_method" => "1",
        "whmcs_ver" => "7.2",
        "whmcs_mod_ver" => $params['templatevars']["moduleParams"]["configoption5"]
    ];
    $apiResult = synergywholesale_hosting_api($resellerId, $apiKey, 'hostingGetService', $data);
    if($apiResult->dedicatedIPv4 == NULL)
        $apiResult->dedicatedIPv4 = 'Dedicated IP has not been configured';
    if(!isset($apiResult->plan))
        return sprintf('<div class="alert alert-danger">%s</div>', htmlentities($apiResult->errorMessage));
    return [
        'templatefile' => 'clientarea',
        'vars' => [
            'plan' => $apiResult->plan,
            'status' => $apiResult->status,
            'server' => $apiResult->server,
            'dedicatedIP' => $apiResult->dedicatedIPv4,
            'serverIP' => $apiResult->serverIPAddress
        ],
    ];
}