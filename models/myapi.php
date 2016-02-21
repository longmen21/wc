<?php
if (!defined('IN_ANWSION')) {
    die;
}

class myapi_class extends AWS_MODEL
{

    public function get_answer_ids($uid)
    {
        return $this->fetch_all('answer', "uid = '" . $uid . "'");
    }

    public function get_answer_favorite_count($answer_id)
    {
        return $this->count('favorite', "item_id = '" . $answer_id . "' AND type = 'article'");
    }

    public function get_user_article_count($uid)
    {
        return $this->count('article', "uid = '" . $uid . "'");
    }

    public function get_clean_user_info($user_info)
    {
        $user_info_key = array('uid', 'user_name', 'signature');

        if (is_array($user_info)) {
            foreach ($user_info as $k => $v) {
                if (!in_array($k, $user_info_key)) unset($user_info[$k]);
            }

            $user_info['avatar_file'] = get_avatar_url($user_info['uid'], 'mid');
        }

        return $user_info;
    }


    public function verify_signature($class_name, $mobile_sign = null)
    {
        if (!$mobile_app_secret = AWS_APP::cache()->get('mobile_app_secret')) //缓存
        {
            if (!$mobile_app_secret = $this->fetch_one('system_setting', 'value', "varname = 'mobile_app_secret'")) {
                return true;  //从未设置  无需验证
            }

            AWS_APP::cache()->set('mobile_app_secret', $mobile_app_secret, 600);
        }

        if (!$mobile_app_secret = unserialize($mobile_app_secret)) {
            return true;  //留白  无需验证 
        }

        if (!$mobile_sign) {
            return false;
        }

        $mobile_app_secret_arr = explode("\n", $mobile_app_secret);

        foreach ($mobile_app_secret_arr as $key => $val) {
            if (md5($class_name . $val) == $mobile_sign) {
                return true;
            }
        }

        return false;
    }

    public function save_mobile_app_secret($mobile_app_secret)
    {
        if ($this->fetch_row('system_setting', "varname = 'mobile_app_secret'"))  //修改
        {
            $this->update('system_setting', array(
                'value' => serialize($mobile_app_secret)
            ), "`varname` = 'mobile_app_secret'");

            $this->update('system_setting', array(
                'value' => serialize(time())
            ), "`varname` = 'mobile_app_secret_update_time'");
        } else  //新增
        {
            $this->insert('system_setting', array(
                'value' => serialize($mobile_app_secret),
                'varname' => 'mobile_app_secret'
            ));

            $this->insert('system_setting', array(
                'value' => serialize(time()),
                'varname' => 'mobile_app_secret_update_time'
            ));
        }

        AWS_APP::cache()->delete('mobile_app_secret');
    }

    public function save_app_log($content)
    {
        return $this->insert('app_log', array(
            'content' => htmlspecialchars($content),
            'add_time' => time()
        ));
    }

    public function get_app_log_list($where = null, $order = 'id DESC', $limit = 10, $page = null)
    {
        return $this->fetch_page('app_log', $where, $order, $page, $limit);
    }

    public function get_posts_list_by_uid($uid, $isMedia = false, $isFamous = false, $post_type, $page = 1, $per_page = 10, $sort = null, $topic_ids = null, $category_id = null, $answer_count = null, $day = 30, $is_recommend = false)
    {
        $order_key = 'add_time DESC';

        switch ($sort) {
            case 'responsed':
                $answer_count = 1;

                break;

            case 'unresponsive':
                $answer_count = 0;

                break;

            case 'new' :
                $order_key = 'update_time DESC';

                break;
        }

        if (is_array($topic_ids)) {
            foreach ($topic_ids AS $key => $val) {
                if (!$val) {
                    unset($topic_ids[$key]);
                }
            }
        }

        if ($topic_ids) {
            $posts_index = $this->get_posts_list_by_topic_ids($post_type, $post_type, $topic_ids, $category_id, $answer_count, $order_key, $is_recommend, $page, $per_page);
        } else {
            $where = array();

            if (isset($answer_count)) {
                $answer_count = intval($answer_count);

                if ($answer_count == 0) {
                    $where[] = "answer_count = " . $answer_count;
                } else if ($answer_count > 0) {
                    $where[] = "answer_count >= " . $answer_count;
                }
            }

            if ($is_recommend) {
                $where[] = 'is_recommend = 1';
            }

            if ($category_id) {
                $where[] = 'category_id IN(' . implode(',', $this->model('system')->get_category_with_child_ids('question', $category_id)) . ')';
            }

            if ($post_type) {
                $where[] = "post_type = '" . $this->quote($post_type) . "'";
            }

            $posts_index = $this->fetch_page('posts_index', implode(' AND ', $where), $order_key, $page, $per_page);

            $this->posts_list_total = $this->found_rows();
        }

        return $this->process_explore_list_data($posts_index);
    }

}
