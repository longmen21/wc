<?php
if (!defined('IN_ANWSION')) {
    die;
}

class famous extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = "white"; //'black'黑名单,黑名单中的检查  'white'白名单,白名单以外的检查

//        if ($this->user_info['permission']['visit_explore'] AND $this->user_info['permission']['visit_site']) {
//            $rule_action['actions'][] = 'famous_users';
//            $rule_action['actions'][] = 'famous_posts';
        $rule_action['actions'] = array(
            'famous_users',
            'famous_posts'
        );

        return $rule_action;
    }

    public function setup()
    {
        //HTTP::no_cache_header();

        if (!$this->model('myapi')->verify_signature(get_class(), $_GET['mobile_sign'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('验签失败')));
        }
    }

    public function index_action()
    {

    }

    public function famous_users_action()
    {
        $per_page = get_setting('contents_per_page');
        $limit = array();
        $page = 1;

        if ($_GET['per_page']) {
            $per_page = intval($_GET['per_page']);
        }

        if ($_GET['page']) {
            $page = intval($_GET['page']);
            if ($page == 0) {
                $page = 1;
            }
        }

        $limit[] = ($page - 1) * $per_page;
        $limit[] = $per_page;

        $users = array_values($this->model('account')->get_users_list('group_id = 100', implode(',', $limit), $attrib = true, $exclude_self = false));

        if(isset($users[0])) {
            foreach($users as $uk => $uv) {
                if ($this->user_id AND $this->model('follow')->user_follow_check($this->user_id, $uv['uid'])) {
                    $users[$uk]['has_focus'] = 1;
                } else {
                    $users[$uk]['has_focus'] = 0;
                }
            }
        }

        H::ajax_json_output(AWS_APP::RSM(array(
            'total_rows' => count($users),
            'rows' => $users
        ), 1, null));
    }

    public function famous_posts_action()
    {
        $per_page = get_setting('contents_per_page');

        if ($_GET['per_page']) {
            $per_page = intval($_GET['per_page']);
        }

        if ($_GET['category']) {
            if (is_digits($_GET['category'])) {
                $category_info = $this->model('system')->get_category_info($_GET['category']);
            } else {
                $category_info = $this->model('system')->get_category_info_by_url_token($_GET['category']);
            }
        }

        if (!$_GET['sort_type'] AND !$_GET['is_recommend']) {
            $_GET['sort_type'] = 'new';
        }

        $posts_list = $this->model('myapi')->get_posts_list_by_uid(null, false, true, null, $_GET['page'], $per_page, $_GET['sort_type'], null, $category_info['id'], $_GET['answer_count'], $_GET['day'], $_GET['is_recommend']);

        $article_key = array('post_type', 'id', 'title', 'message', 'add_time', 'views', 'votes', 'topics', 'user_info');
        $topics_key = array('topic_id', 'topic_title');
        $user_info_key = array('uid', 'user_name');

        if ($posts_list) {
            foreach ($posts_list as $key => $val) {
                $posts_list_key = $article_key;

                foreach ($val as $k => $v) {
                    if (!in_array($k, $posts_list_key)) unset($posts_list[$key][$k]);
                }

                if ($val['user_info']) {
                    foreach ($val['user_info'] as $k => $v) {
                        if (!in_array($k, $user_info_key)) unset($posts_list[$key]['user_info'][$k]);
                    }

                    $posts_list[$key]['user_info']['avatar_file'] = get_avatar_url($posts_list[$key]['user_info']['uid'], 'mid');
                }

                if (is_array($val['topics'])) {
                    foreach ($val['topics'] as $kk => $vv) {
                        foreach ($vv as $k => $v) {
                            if (!in_array($k, $topics_key)) unset($posts_list[$key]['topics'][$kk][$k]);
                        }
                    }
                }

                if (is_array($val['answer_users'])) {
                    foreach ($val['answer_users'] as $kk => $vv) {
                        foreach ($vv as $k => $v) {
                            if (!in_array($k, $user_info_key)) unset($posts_list[$key]['answer_users'][$kk][$k]);
                        }

                        $posts_list[$key]['answer_users'][$kk]['avatar_file'] = get_avatar_url($posts_list[$key]['answer_users'][$kk]['uid'], 'mid');
                    }
                }

                if ($posts_list[$key]['category_id'] == '2') { //
                    $tmp = unserialize(htmlspecialchars_decode($posts_list[$key]['message']));
                    $posts_list[$key]['outline'] = $tmp['outline'];
                    $posts_list[$key]['imgUrl'] = $tmp['imgUrl'];
                    $posts_list[$key]['url'] = $tmp['url'];
                    $posts_list[$key]['message'] = "";
                } else {
                    $posts_list[$key]['outline'] = null;
                    $posts_list[$key]['imgUrl'] = $this->model('myapi')->get_image($posts_list[$key]['message']);
                    $posts_list[$key]['url'] = null;
                }

            }
        } else {
            $posts_list = array();
        }

        H::ajax_json_output(AWS_APP::RSM(array(
            'total_rows' => count($posts_list),
            'rows' => array_values($posts_list)
        ), 1, null));
    }


}