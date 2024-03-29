<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_helper
{
    public static $logged_user = null;
    function __construct($id)
    {
        $CI = & get_instance();

        $CI->db->from($CI->config->item('system_db_login').'.'.$CI->config->item('table_login_setup_user_info').' user_info');
        $CI->db->where('user_info.user_id',$id);
        $CI->db->where('user_info.revision',1);

        $user=$CI->db->get()->row();
        if ($user)
        {
            foreach ($user as $key => $value)
            {
                $this->$key = $value;
            }
        }
        $this->user_group=0;
        $assigned_group=$CI->db->get_where($CI->config->item('system_db_tms').'.'.$CI->config->item('table_system_assigned_group'), array('user_id' => $id,'revision'=>1))->row();
        if($assigned_group)
        {
            $this->user_group=$assigned_group->user_group;
        }
    }
    public static function login($username, $password)
    {
        //also need to check if it has access to ems
        $CI = & get_instance();

        $CI->db->from($CI->config->item('system_db_login').'.'.$CI->config->item('table_login_setup_user').' user');
        $CI->db->select('user.id');
        $CI->db->join($CI->config->item('system_db_login').'.'.$CI->config->item('table_login_setup_users_other_sites').' uos','uos.user_id=user.id','inner');
        $CI->db->join($CI->config->item('system_db_login').'.'.$CI->config->item('table_login_system_other_sites').' os','uos.site_id=os.id','inner');
        $CI->db->where('uos.revision',1);
        $CI->db->where('os.short_name',$CI->config->item('system_site_short_name'));

        $CI->db->where('user.user_name',$username);
        $CI->db->where('user.password',md5($password));
        $CI->db->where('user.status',$CI->config->item('system_status_active'));

        $user=$CI->db->get()->row();

        if ($user)
        {
            $CI->session->set_userdata("user_id", $user->id);
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }



    public static function get_user()
    {
        $CI = & get_instance();
        if (User_helper::$logged_user)
        {
            return User_helper::$logged_user;
        }
        else
        {
            if($CI->session->userdata("user_id")!="")
            {
                $CI->db->from($CI->config->item('system_db_login').'.'.$CI->config->item('table_login_setup_user').' user');
                $CI->db->select('user.id');

                $CI->db->join($CI->config->item('system_db_login').'.'.$CI->config->item('table_login_setup_users_other_sites').' uos','uos.user_id=user.id','inner');
                $CI->db->join($CI->config->item('system_db_login').'.'.$CI->config->item('table_login_system_other_sites').' os','uos.site_id=os.id','inner');
                $CI->db->where('uos.revision',1);
                $CI->db->where('os.short_name',$CI->config->item('system_site_short_name'));

                $CI->db->where('user.id',$CI->session->userdata('user_id'));
                $CI->db->where('user.status',$CI->config->item('system_status_active'));
                $user=$CI->db->get()->row();

                if($user)
                {
                    User_helper::$logged_user = new User_helper($CI->session->userdata('user_id'));
                    return User_helper::$logged_user;
                }
                return null;
            }
            else
            {
                return null;
            }

        }
    }
    public static function get_html_menu()
    {
        $user=User_helper::get_user();
        $CI = & get_instance();
        $CI->db->order_by('ordering');
        $tasks=$CI->db->get($CI->config->item('system_db_tms').'.'.$CI->config->item('table_system_task'))->result_array();

        $roles=Query_helper::get_info($CI->config->item('system_db_tms').'.'.$CI->config->item('table_system_user_group_role'),'*',array('revision =1','action0 =1','user_group_id ='.$user->user_group));
        $role_data=array();
        foreach($roles as $role)
        {
            $role_data[]=$role['task_id'];

        }
        $menu_data=array();
        foreach($tasks as $task)
        {
            if($task['type']=='TASK')
            {
                if(in_array($task['id'],$role_data))
                {
                    $menu_data['items'][$task['id']]=$task;
                    $menu_data['children'][$task['parent']][]=$task['id'];
                }
            }
            else
            {
                $menu_data['items'][$task['id']]=$task;
                $menu_data['children'][$task['parent']][]=$task['id'];
            }
        }

        $html='';
        if(isset($menu_data['children'][0]))
        {
            foreach($menu_data['children'][0] as $child)
            {
                $html.=User_helper::get_html_submenu($child,$menu_data,1);
            }
        }
        return $html;



        //return User_helper::get_html_submenu(0,$menu_data,1);

    }
    public static function get_html_submenu($parent,$menu_data,$level)
    {
        if(isset($menu_data['children'][$parent]))
        {
            $sub_html='';
            foreach($menu_data['children'][$parent] as $child)
            {
                $sub_html.=User_helper::get_html_submenu($child,$menu_data,$level+1);

            }
            $html='';
            if($sub_html)
            {
                if($level==1)
                {
                    $html.='<li class="menu-item dropdown">';
                    $html.='<a href="#" class="dropdown-toggle" data-toggle="dropdown">'.$menu_data['items'][$parent]['name'].'<b class="caret"></b></a>';
                }
                else
                {
                    $html.='<li class="menu-item dropdown dropdown-submenu">';
                    $html.='<a href="#" class="dropdown-toggle" data-toggle="dropdown">'.$menu_data['items'][$parent]['name'].'</a>';
                }

                $html.='<ul class="dropdown-menu">';
                $html.=$sub_html;
                $html.='</ul></li>';
            }

            return $html;

        }
        else
        {
            if($menu_data['items'][$parent]['type']=='TASK')
            {
                return '<li><a href="'.site_url(strtolower($menu_data['items'][$parent]['controller'])).'">'.$menu_data['items'][$parent]['name'].'</a></li>';
            }
            else
            {
                return '';
            }

        }
    }
    public static function get_permission($controller_name)
    {
        $CI = & get_instance();
        $user=User_helper::get_user();
        $CI->db->from($CI->config->item('system_db_tms').'.'.$CI->config->item('table_system_user_group_role').' ugr');
        $CI->db->select('ugr.*');

        $CI->db->join($CI->config->item('system_db_tms').'.'.$CI->config->item('table_system_task').' task','task.id = ugr.task_id','INNER');
        $CI->db->where("controller",$controller_name,"after");
        $CI->db->where("user_group_id",$user->user_group);
        $CI->db->where("revision",1);
        $result=$CI->db->get()->row_array();
        return $result;
    }
}