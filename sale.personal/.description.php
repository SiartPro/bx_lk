<?
/**
 * Created by PhpStorm.
 * @author Karikh Dmitriy <demoriz@gmail.com>
 * @date 19.08.2020
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
	"NAME" => "Личный кабинет",
	"DESCRIPTION" => "Личный кабинет",
	"ICON" => "/images/icon.gif",
	"SORT" => 10,
	"CACHE_PATH" => "Y",
	"PATH" => array(
		"ID" => "Project", // for example "my_project"
		/*"CHILD" => array(
			"ID" => "", // for example "my_project:services"
			"NAME" => "",  // for example "Services"
		),*/
	),
	"COMPLEX" => "N",
);

?>