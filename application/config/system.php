<?php
$config['system_site_short_name']='tms';
$config['offline_controllers']=array('home','sys_site_offline');
$config['external_controllers']=array('home');//user can use them without login
$config['system_max_actions']=7;

//dbs
$config['system_db_tms']='arm_tms';
$config['system_db_login']='arm_login';
$config['system_db_ems']='arm_ems';



$config['system_status_active']='Active';
$config['system_status_inactive']='In-Active';
$config['system_status_delete']='Deleted';

$config['system_image_base_url']='http://localhost/tms/';