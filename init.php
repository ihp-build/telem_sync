<?include_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/php_interface/bitronic_ini.php") ;?>
<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/components/yenisite/catalog.sets/userprop.php") ;?>
<?
/*----------------------ZABA------------------*/

AddEventHandler("sale", "OnOrderNewSendEmail", "bxModifySaleMails");

//-- Собственно обработчик события

function bxModifySaleMails($orderID, &$eventName, &$arFields)
{

	$arOrder = CSaleOrder::GetByID($orderID);

	//-- получаем телефоны и адрес
	$order_props = CSaleOrderPropsValue::GetOrderProps($orderID);

	$kozebojka = '';

	$index = "";
	$country_name = "";
	$city_name = "";
	$address = "";

	while ($arProps = $order_props->Fetch())
	{

		if ($arProps["CODE"] == "PHONE")
		{
			$kozebojka = $kozebojka . 'Телефон: ' . htmlspecialchars($arProps["VALUE"]) . '<br/>';
		}



		if ($arProps["CODE"] == "LOCATION")
		{
			$arLocs = CSaleLocation::GetByID($arProps["VALUE"]);
			$country_name =  $arLocs["COUNTRY_NAME"];
			$city_name = $arLocs["CITY_NAME"];
		}

		if ($arProps["CODE"] == "ZIP")
		{
			$index = $arProps["VALUE"];
		}

		if ($arProps["CODE"] == "ADDRESS")
		{
			$address = $arProps["VALUE"];
		}

	}

	$full_address = $index.", ".$country_name."-".$city_name.", ".$address;
	$kozebojka = $kozebojka . 'Адрес: ' . $full_address . '<br/>';

	//-- получаем название службы доставки
	$arDeliv = CSaleDelivery::GetByID($arOrder["DELIVERY_ID"]);

	if ($arDeliv)
	{
		$kozebojka = $kozebojka . 'Доставка: ' . $arDeliv["NAME"]. '<br/>';
	}

	//-- получаем название платежной системы
	$arPaySystem = CSalePaySystem::GetByID($arOrder["PAY_SYSTEM_ID"]);
	if ($arPaySystem)
	{
		$kozebojka = $kozebojka . 'Оплата: ' . $arPaySystem["NAME"]. '<br/>';
	}

	$kozebojka = $kozebojka . 'Примечание: ' . $arOrder["USER_DESCRIPTION"];

	$arFields["ADDED_FEEL"] = $kozebojka;
}




AddEventHandler("iblock", "OnBeforeIBlockElementDelete", "KozeOnElDelAfter");





function KozeOnElDelAfter($ID){
	global $USER;
	CModule::IncludeModule('iblock');

	if(($USER->isAdmin())/*&&($USER->GetLogin() == 'zaba')*/){
		if(!isset($GLOBALS['zabafirstadd'])) {
			$GLOBALS['zabafirstadd'] = true;
			$idel = getUnElementZa($ID);
			CIBlockElement::Delete($idel['ID']);
		}
	}
}

function setGlobalIDUp($id){
	$GLOBALS['zabafirstid'] = $id;
}

function getGlobalIDUp(){
	return $GLOBALS['zabafirstid'];
}


function getFullProps($idel = false){
	CModule::IncludeModule('iblock');

	$resource = CIBlockElement::GetByID($idel);
	if ($ob = $resource->GetNextElement()){
		$arFields = $ob->GetFields();
		$arFields['PROPERTIES'] = $ob->GetProperties();


		$arFields['PROPERTY_VALUES'] = array();

		foreach ($arFields['PROPERTIES'] as $property){

			if ($property['PROPERTY_TYPE']=='L'){
				if ($property['MULTIPLE']=='Y'){
					$arFields['PROPERTY_VALUES'][$property['CODE']] = array();
					foreach($property['VALUE_ENUM_ID'] as $enumID){
						$arFields['PROPERTY_VALUES'][$property['CODE']][] = array(
							'VALUE' => $enumID
						);
					}
				} else {
					$arFields['PROPERTY_VALUES'][$property['CODE']] = array(
						'VALUE' => $property['VALUE_ENUM_ID']
					);
				}
			}
			elseif ($property['PROPERTY_TYPE']=='F')
			{				
				if ($property['MULTIPLE']=='Y') {
					if (is_array($property['VALUE']))
					{

						$pr_in_clone = array();
						$pr_in_clone[$property['CODE']] = getFileMultipleProperty(getGlobalIDUp(), $property['CODE']);
						
						foreach ($property['VALUE'] as $key => $arElEnum)
						{
							if ( is_array($pr_in_clone[$property['CODE']]['VALUE']) )
							{
								if ( !in_array($arElEnum, $pr_in_clone[$property['CODE']]['VALUE']))
									$arFields['PROPERTY_VALUES'][$property['CODE']][$key] = $arElEnum;
							}
							else
								$arFields['PROPERTY_VALUES'][$property['CODE']][$key] = $arElEnum;
						}
						
						if ( is_array($pr_in_clone[$property['CODE']]['VALUE']) )
						{
							foreach ($pr_in_clone[$property['CODE']]['VALUE'] as $pr_key => $pr_value)
							{
								if ( !in_array($pr_value, $property['VALUE']) )
								{
									CIBlockElement::SetPropertyValueCode(getGlobalIDUp(), $property['CODE'], array($pr_in_clone[$property['CODE']]['PROPERTY_VALUE_ID'][$pr_key] => array('del' => 'Y')));
								}
							}
						}
					}
					else
					{
						$element = getBasicElementData(getGlobalIDUp());
						CIBlockElement::SetPropertyValuesEx(getGlobalIDUp(), $element["IBLOCK_ID"], array($property['CODE'] => array("VALUE" => array("del" => "Y"))));
					}
				}
				else
				{
					if ( trim($property['VALUE']) == "" )
					{
						$element = getBasicElementData(getGlobalIDUp());

						CIBlockElement::SetPropertyValuesEx(getGlobalIDUp(), $element["IBLOCK_ID"], array($property['CODE'] => array("VALUE" => array("del" => "Y"))));
						
						$arFields['PROPERTY_VALUES'][$property['CODE']] = "";
					}
					else
					{
						$arFields['PROPERTY_VALUES'][$property['CODE']] = $property['VALUE'];
					}
				}
			}
			else{
				$arFields['PROPERTY_VALUES'][$property['CODE']] = $property['VALUE'];
			}
		}
		


		unset($arFields['ID'], $arFields['TMP_ID'], $arFields['WF_LAST_HISTORY_ID'], $arFields['SHOW_COUNTER'], $arFields['SHOW_COUNTER_START'], $arFields['XML_ID'], $arFields['EXTERNAL_ID']);
		unset($arFields['~ID'], $arFields['~TMP_ID'], $arFields['~WF_LAST_HISTORY_ID'], $arFields['~SHOW_COUNTER'], $arFields['~SHOW_COUNTER_START'], $arFields['~XML_ID'], $arFields['~EXTERNAL_ID']);

		return $arFields;
	}
}

function getFileMultipleProperty($element_id, $property_code)
{
	$resource = CIBlockElement::GetByID($element_id);
	if ($ob = $resource->GetNextElement())
	{
		$arF = array();
		$arF['PROPERTIES'] = $ob->GetProperties();
		
		return $arF['PROPERTIES'][$property_code];
	}
	return false;
}

function getBasicElementData($idel = false) {
	CModule::IncludeModule('iblock');

	$resource = CIBlockElement::GetByID($idel);
	if ($ob = $resource->GetNextElement()){
		$arFields = $ob->GetFields();
		return $arFields;
	}
	return false;
}

function getUnElementZa($id){

	$fp = getBasicElementData($id); /*getFullProps($id);*/
	$newiblock = $fp['IBLOCK_CODE'];
	if(substr($newiblock, 0, 3) == 'nt_'){
		$newiblock = substr($newiblock, 3);
	}else{
		$newiblock = 'nt_'.$newiblock;
	}

	$res = CIBlockElement::GetList(array(), array('IBLOCK_CODE'=>$newiblock, 'NAME'=>$fp['NAME']));
	$ob = $res->GetNextElement();
	return $ob->GetFields();
}

function getUnIBlockZa($id){
	$fp = getFullProps($id);
	$newiblock = $fp['IBLOCK_CODE'];
	if(substr($newiblock, 0, 3) == 'nt_'){
		$newiblock = substr($newiblock, 3);
	}else{
		$newiblock = 'nt_'.$newiblock;
	}

	return $newiblock;
}



AddEventHandler("iblock", "OnBeforeIBlockElementUpdate", "KozeOnElUpdBefore");

function KozeOnElUpdBefore(&$arFields){
	global $USER;
	CModule::IncludeModule('iblock');

	if(($USER->isAdmin())/*&&($USER->GetLogin() == 'zaba')*/){
		if(!isset($GLOBALS['zabafirstadd'])) {
			$idel = getUnElementZa($arFields['ID']);
			setGlobalIDUp($idel['ID']);
		}
	}
}


AddEventHandler("iblock", "OnAfterIBlockElementUpdate", "KozeOnElUpd");

function KozeOnElUpd(&$arFields){
	global $USER;
	CModule::IncludeModule('iblock');

	if(($USER->isAdmin())/*&&($USER->GetLogin() == 'zaba')*/){
		if(!isset($GLOBALS['zabafirstadd'])){
			$GLOBALS['zabafirstadd'] = true;

			$updpro = getFullProps($arFields['ID']);
			$updproz = $arFields;
			$updproz['PROPERTY_VALUES'] = $updpro['PROPERTY_VALUES'];
			unset($updproz['ID'], $updproz['TMP_ID'], $updproz['WF_LAST_HISTORY_ID'], $updproz['SHOW_COUNTER'], $updproz['SHOW_COUNTER_START'], $updproz['XML_ID'], $updproz['EXTERNAL_ID']);
			unset($updproz['~ID'], $updproz['~TMP_ID'], $updproz['~WF_LAST_HISTORY_ID'], $updproz['~SHOW_COUNTER'], $updproz['~SHOW_COUNTER_START'], $updproz['~XML_ID'], $updproz['~EXTERNAL_ID']);


			$el = new CIBlockElement();
			$el->Update(getGlobalIDUp(), $updproz);
		}
	}

}


AddEventHandler("iblock", "OnAfterIBlockElementAdd", "KozeOnElAdd");

function KozeOnElAdd(&$arFields){
	global $USER;
	CModule::IncludeModule('iblock');

	if(($USER->isAdmin())/*&&($USER->GetLogin() == 'zaba')*/){
		if(!isset($GLOBALS['zabafirstadd'])){
			$GLOBALS['zabafirstadd'] = true;
			copyElProp($arFields['ID'],$arFields);
		}
	}
}



function copyElProp($idel,$fieldses){

	$resource = CIBlockElement::GetByID($idel);
	if ($ob = $resource->GetNextElement()){

		$arFields = $ob->GetFields();

		$arFields['PROPERTIES'] = $ob->GetProperties();

		$arFieldsCopy = $fieldses;
		$arFields['PROPERTY_VALUES'] = array();

		foreach ($arFields['PROPERTIES'] as $property){

			if ($property['PROPERTY_TYPE']=='L'){
				if ($property['MULTIPLE']=='Y'){
					$arFields['PROPERTY_VALUES'][$property['CODE']] = array();
					foreach($property['VALUE_ENUM_ID'] as $enumID){
						$arFields['PROPERTY_VALUES'][$property['CODE']][] = array(
							'VALUE' => $enumID
						);
					}
				} else {
					$arFields['PROPERTY_VALUES'][$property['CODE']] = array(
						'VALUE' => $property['VALUE_ENUM_ID']
					);
				}
			}
			elseif ($property['PROPERTY_TYPE']=='F')
			{
				if ($property['MULTIPLE']=='Y') {
					if (is_array($property['VALUE']))
					{
						foreach ($property['VALUE'] as $key => $arElEnum)
							$arFields['PROPERTY_VALUES'][$property['CODE']][$key] = $arElEnum;
					}
				}else $arFields['PROPERTY_VALUES'][$property['CODE']] = $property['VALUE'];
			}
			else{
				$arFields['PROPERTY_VALUES'][$property['CODE']] = $property['VALUE'];
			}
		}

		$arFieldsCopy['PROPERTY_VALUES'] = $arFields['PROPERTY_VALUES'];

		unset($arFieldsCopy['ID'], $arFieldsCopy['TMP_ID'], $arFieldsCopy['WF_LAST_HISTORY_ID'], $arFieldsCopy['SHOW_COUNTER'], $arFieldsCopy['SHOW_COUNTER_START'], $arFieldsCopy['XML_ID'], $arFieldsCopy['EXTERNAL_ID']);
		unset($arFieldsCopy['~ID'], $arFieldsCopy['~TMP_ID'], $arFieldsCopy['~WF_LAST_HISTORY_ID'], $arFieldsCopy['~SHOW_COUNTER'], $arFieldsCopy['~SHOW_COUNTER_START'], $arFieldsCopy['~XML_ID'], $arFieldsCopy['~EXTERNAL_ID']);


		$el = new CIBlockElement();



		$arbli = CIBlock::GetList(Array(),Array("CODE"=>getUnIBlockZa($idel)));

		$arblis = $arbli->Fetch();
			$arFieldsCopy['IBLOCK_ID'] = $arblis['ID'];

//		echo('<pre>');
//		print_r($arFieldsCopy);
//		exit;

		$el->Add($arFieldsCopy);

	}
}


?>
