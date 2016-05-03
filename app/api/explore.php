<?php
if (!defined('IN_ANWSION')) {
    die;
}

class explore extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = "white"; //'black'黑名单,黑名单中的检查  'white'白名单,白名单以外的检查

        if ($this->user_info['permission']['visit_explore'] AND $this->user_info['permission']['visit_site']) {
            $rule_action['actions'][] = 'index';
        }

        return $rule_action;
    }

    public function setup()
    {
        //HTTP::no_cache_header();

        if (!$this->model('myapi')->verify_signature(get_class(), $_GET['mobile_sign'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('验签失败')));
        }
    }


    //GET: category(选),per_page(选，默认:10),sort_type([new,hot]选,默认:最新),page(默认1),day(默认30),is_recommend
    public function index_action()
    {
        $page = 1;
        $per_page = get_setting('contents_per_page');
        $isMedia = false;
        $isFamous = false;

        if ($_GET['isMedia']) {
            $isMedia = $_GET['isMedia'];
        }

        if ($_GET['isFamous']) {
            $isFamous = $_GET['isFamous'];
        }

        if ($_GET['per_page']) {
            $per_page = intval($_GET['per_page']);
        }

        if ($_GET['page']) {
            $page = intval($_GET['page']);
            if ($page == 0) {
                $page = 1;
            }
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

//       $uid, $isMedia = false, $isFamous = false, $post_type, $page = 1, $per_page = 10, $sort = null, $topic_ids = null,
//                                          $category_id = null, $answer_count = null, $day = 30, $is_recommend = false)

        if ($_GET['uid'] || $isFamous || $isMedia) {
            $posts_list = $this->model('myapi')->get_posts_list_by_uid($_GET['uid'], $isMedia, $isFamous, null, $page, $per_page, $_GET['sort_type'], null, $category_info['id'], $_GET['answer_count'], $_GET['day'], $_GET['is_recommend']);
        } else {
            if ($_GET['sort_type'] == 'hot') {
                $posts_list = $this->model('posts')->get_hot_posts(null, $category_info['id'], null, $_GET['day'], $page, $per_page);
            } else {
                $posts_list = $this->model('posts')->get_posts_list(null, $page, $per_page, $_GET['sort_type'], null, $category_info['id'], $_GET['answer_count'], $_GET['day'], $_GET['is_recommend']);
            }

            if ($posts_list) {
                foreach ($posts_list AS $key => $val) {
                    if ($val['answer_count']) {
                        $posts_list[$key]['answer_users'] = $this->model('question')->get_answer_users_by_question_id($val['question_id'], 2, $val['published_uid']);
                    }
                }
            }
        }

//        $question_key = array('post_type', 'question_id', 'question_content', 'add_time', 'answer_count', 'view_count', 'agree_count', 'against_count', 'answer_users', 'topics', 'user_info');
        $article_key = array('associate_type', 'post_type', 'id', 'title', 'message', 'add_time', 'views', 'votes', 'topics', 'user_info', 'outline', 'imgUrl', 'url', 'category_id');
        $topics_key = array('topic_id', 'topic_title');
        $user_info_key = array('uid', 'user_name');

        if ($posts_list) {
            foreach ($posts_list as $key => $val) {
                $posts_list_key = $article_key;

                if ($val['post_type'] == 'question') {
                    unset($posts_list[$key]);
                    continue;
                }

                foreach ($val as $k => $v) {
                    if (!in_array($k, $posts_list_key)) unset($posts_list[$key][$k]);
                }

                if ($val['user_info']) {
                    foreach ($val['user_info'] as $k => $v) {
                        if (!in_array($k, $user_info_key)) unset($posts_list[$key]['user_info'][$k]);
                        if ($this->model('follow')->user_follow_check($this->user_id, $v['uid'])) {
                            $posts_list[$key]['user_info']['has_focus'] = 1;
                        } else {
                            $posts_list[$key]['user_info']['has_focus'] = 0;
                        }
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

                if (is_null($posts_list[$key]['url'])) {
                    $data[$key]['url'] = "";
                }

                if ($posts_list[$key]['category_id'] == '2') { //
//                    $tmp = unserialize(htmlspecialchars_decode($posts_list[$key]['message']));
                    $posts_list[$key]['outline'] = $posts_list[$key]['outline'] ? $posts_list[$key]['outline'] : "";
//                    $posts_list[$key]['imgUrl'] = $tmp['imgUrl'] ? 'http://' . $_SERVER['HTTP_HOST'] . '/' .$tmp['imgUrl'] : "";
//                    $posts_list[$key]['url'] = $tmp['url'] ? $tmp['url'] : $posts_list[$key]['url'];
                    $posts_list[$key]['imgUrl'] = $posts_list[$key]['imgUrl'] ? 'http://' . $_SERVER['HTTP_HOST'] . '/' . $posts_list[$key]['imgUrl'] : "";
                } else {
                    $posts_list[$key]['outline'] = "";
                    $posts_list[$key]['imgUrl'] = $this->model('myapi')->get_image($posts_list[$key]['message']);
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