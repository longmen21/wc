<?php
if (!defined('IN_ANWSION')) {
    die;
}

class home extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = "white"; //'black'黑名单,黑名单中的检查  'white'白名单,白名单以外的检查
        $rule_action['actions'] = array(
            'index'
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
        if (!$this->user_id) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请先登录或注册')));
        }

        $per_page = get_setting('contents_per_page');

        if ($_GET['per_page']) {
            $per_page = intval($_GET['per_page']);
        }

        //$data = $this->model('myhome')->home_activity($this->user_id, (intval($_GET['page']) * $this->per_page) . ", {$this->per_page}");
        $limit = (intval($_GET['page']) * $per_page) . ", {$per_page}";
        if ($_GET['uid']) {
            $data = $this->model('actions')->get_user_actions($_GET['uid'], null, '501,502,503', $this->user_id);
            $tmp = $this->model('myapi')->get_favorite_lists($_GET['uid']);
            if (isset($tmp[0])) {
                $data = array_merge($data, $tmp);
                $data = $this->model('myapi')->data_sort($data);
                $data = array_slice($data, intval($_GET['page']) * $per_page, $per_page);
            }
        } else {
            $data = $this->model('actions')->home_activity($this->user_id, $limit);
        }

        if (!is_array($data)) {
            $data = array();
        } else {
            //$data_key = array('history_id', 'associate_action', 'user_info', 'answer_info', 'question_info', 'article_info', 'comment_info', 'add_time');
            $data_key = array('history_id', 'uid', 'associate_action', 'user_info', 'article_info', 'comment_info', 'add_time');
            $user_info_key = array('uid', 'user_name', 'signature');
            $article_info_key = array('id', 'title', 'message', 'comments', 'views', 'add_time', 'outline', 'imgUrl', 'url', 'category_id');
            //$answer_info_key = array('answer_id', 'answer_content', 'add_time', 'against_count', 'agree_count');
            //$question_info_key = array('question_id', 'question_content', 'add_time', 'update_time', 'answer_count', 'agree_count');

            foreach ($data as $key => $val) {
//                if($_GET['uid']) {
//                    if ($data[$key]['uid'] != $_GET['uid']) {
//                        unset($data[$key]);
//                        continue;
//                    }
//                }
//                if($data[$key]['associate_action'] != 502) {
//                    unset($data[$key]);
//                        continue;
//                }
                if ($val['associate_action'] == 503) {
                    $data[$key]['comment_info'] = $this->model('myapi')->get_user_comments($data[$key]['history_id']);
                }

                foreach ($val as $k => $v) {
                    if (!in_array($k, $data_key)) unset($data[$key][$k]);
                }

                if ($val['user_info']) {
//                    if($val['user_info']['uid'] != $uid) {
//                        unset($data[$key]);
//                        break;
//                    }
                    foreach ($val['user_info'] as $k => $v) {
                        if (!in_array($k, $user_info_key)) unset($data[$key]['user_info'][$k]);
                        if ($this->model('follow')->user_follow_check($this->user_id, $v['uid'])) {
                            $data[$key]['user_info']['has_focus'] = 1;
                        } else {
                            $data[$key]['user_info']['has_focus'] = 0;
                        }
                    }

                    $data[$key]['user_info']['avatar_file'] = get_avatar_url($data[$key]['user_info']['uid'], 'mid');
                }

                if ($val['article_info']) {
                    foreach ($val['article_info'] as $k => $v) {
                        if (!in_array($k, $article_info_key)) unset($data[$key]['article_info'][$k]);
                    }
                }

                if ($val['associate_action'] == 502) {
                    $arrs = $this->model('article')->get_article_info_by_id($val['associate_id']);
                    foreach ($arrs as $k => $v) {
                        if (!in_array($k, $article_info_key)) unset($arrs[$k]);
                    }
                    $data[$key]['article_info'] = $arrs;
                }

//                if ($val['answer_info']) {
//                    foreach ($val['answer_info'] as $k => $v) {
//                        if (!in_array($k, $answer_info_key)) unset($data[$key]['answer_info'][$k]);
//                    }
//                }
//
//                if ($val['question_info']) {
//                    foreach ($val['question_info'] as $k => $v) {
//                        if (!in_array($k, $question_info_key)) unset($data[$key]['question_info'][$k]);
//                    }
//                }

                if ($data[$key]['article_info']['category_id'] == 2) { //
                    $tmp = unserialize(htmlspecialchars_decode($data[$key]['article_info']['message']));
                    $data[$key]['outline'] = $tmp['outline'] ? $tmp['outline'] : "";
                    $data[$key]['imgUrl'] = $tmp['imgUrl'] ? 'http://' . $_SERVER['HTTP_HOST'] . '/' .$tmp['imgUrl'] : "";
                    $data[$key]['url'] = $tmp['url'] ? $tmp['url'] : "";
                    $data[$key]['article_info']['message'] = '';
                } else {
                    $data[$key]['outline'] = '';
                    $data[$key]['imgUrl'] = $this->model('myapi')->get_image($data[$key]['article_info']['message']);
//
////                    if(cjk_strlen($data[$key]['article_info']['message']) > 130) {
////                        $data[$key]['article_info']['message'] = cjk_substr(strip_ubb($data[$key]['article_info']['message']), 0, 130, 'UTF-8', '...');
////                    }
                }
            }
        }
        H::ajax_json_output(AWS_APP::RSM(array(
            'total_rows' => count($data),
            'rows' => array_values($data)
        ), 1, null));
    }
}