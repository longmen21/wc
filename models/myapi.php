<?php
if (!defined('IN_ANWSION')) {
    die;
}
include 'phpQuery/phpQuery.php';

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
                return true; //从未设置  无需验证
            }
            AWS_APP::cache()->set('mobile_app_secret', $mobile_app_secret, 600);
        }
        if (!$mobile_app_secret = unserialize($mobile_app_secret)) {
            return true; //留白  无需验证
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
        if ($this->fetch_row('system_setting', "varname = 'mobile_app_secret'")) //修改
        {
            $this->update('system_setting', array(
                'value' => serialize($mobile_app_secret)
            ), "`varname` = 'mobile_app_secret'");
            $this->update('system_setting', array(
                'value' => serialize(time())
            ), "`varname` = 'mobile_app_secret_update_time'");
        } else //新增
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

    public function get_posts_list_by_uid($uid, $isMedia = false, $isFamous = false,
                                          $post_type, $page = 1, $per_page = 10, $sort = null,
                                          $category_id = null, $answer_count = null, $day = 30, $is_recommend = false)
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
        $where = array();
        if ($uid) {
            $where[] = "uid = " . intval($uid);
        } else {
            $group_id = 0;
            if ($isFamous) {
                $group_id = 100;
            } else {
                $group_id = 101;
            }
            $this->select('uid');
            $user_index = $this->fetch_all('users', 'group_id = ' . $group_id);
            $str = "uid in (";
            foreach ($user_index as $key => $val) {
                $str .= $val['uid'];
                if ($key != sizeof($user_index) - 1) {
                    $str .= ',';
                }
            }
            $str .= ")";
            $where[] = $str;
        }
        $where[] = "answer_count = " . '0';
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
        return $this->model('posts')->process_explore_list_data($posts_index);
    }

    public function publish_article_by_url($url = false, $uid = false)
    {
        $pics = array();
        if (!$url = $this->ping_url($url)) {
            return '-2'; //
        }

        phpQuery::newDocumentFile($url);
        $title = pq('title')->text();
        if (!$outline = pq('meta[name=description]')->attr('content')) {
            $outline = $title . '...';
        }

        $imgs = pq('img');
        $i = 0;
        foreach ($imgs as $img) {
            if ($i > 10) {
                break;
            }
            if (pq($img)->attr('data-src')) {
                $pic = pq($img)->attr('data-src');
            } else {
                $pic = pq($img)->attr('src');
            }
            if ($pic) {
                $t = getimagesize($pic);
                $pics[] = array($t[0], $t[1], $pic);
            }
            $i++;
        }

        if (count($pics) != 0) {
//            var_dump($pics);
//            exit;

            $imgUrl = $this->select_max_img($pics);

            if (strpos($imgUrl, 'http://') == -1) {
                // $img_url = $img_url;
                $domain_url = substr($url, 0, strpos($url, '/', 8) + 1);
                $imgUrl = $domain_url . $imgUrl;
            }
//        echo $imgUrl;exit;

            $filePath = 'uploads/share/'; //
            if (!$picPath = $this->save_img($imgUrl, $filePath)) { //
                return '-1'; //
            }
        } else {
            $picPath = '';
        }

        $article_id = $this->model('publish')->publish_article($title,
            serialize(array('url' => $url, 'imgUrl' => $picPath, 'outline' => $outline)),
            $uid, null, 2, null,
            null);

        return $article_id;
    }

    private function select_max_img($pics)
    {
        $imgUrl = '';
        if (isset($pics[0])) {
            $great_size = $pics[0][0] * $pics[0][1];
            $big = 0;
            foreach ($pics as $pk => $pv) {
                if ($pv[0] * $pv[1] > $great_size) {
                    $big = $pk;
                    $great_size = $pv[0] * $pv[1];
                }
            }
            $imgUrl = $pics[$big][2];
        }
        return $imgUrl;
    }

    private function ping_url($url = false)
    {
        if (!strpos($url, '//')) {
            $url = 'http://' . $url;
        }

        if (@fopen($url, 'r')) {
            return $url;
        } else {
            return false;
        }
    }

    private function save_img($url, $filepath)
    {
        @!is_dir($filepath) ? mkdir($filepath) : null;
        $filetime = time();
        $filename = date("YmdHis", $filetime) . rand(100, 999);
        $img = file_get_contents($url);

        $ext = image_type_to_extension(exif_imagetype($url));

        file_put_contents($filepath . $filename . $ext, $img);

        return $filepath . $filename . $ext;
    }

    public function get_image($msg)
    {
        $imgUrl = '';
        if (strstr($msg, 'attach')) {
            $attachs = FORMAT::parse_attachs(FORMAT::parse_bbcode($msg), true);
            $imgUrl = $this->model('publish')->get_attach_by_id($attachs[0])['attachment'];
        } else if (strstr($msg, 'img')) {
            preg_match_all('#\[img\](.*)\[/img\]#', $msg, $imgs);
            if (isset($imgs[1][0])) {
                $imgUrl = $imgs[1][0];
            }
        }
        return $imgUrl == null ? '' : $imgUrl;
    }

    public function get_user_comments($history_id)
    {
        $rs = $this->query_all('select b.id,b.message,b.add_time,b.votes
from wecenter.aws_user_action_history a join wecenter.aws_article_comments b on a.add_time = b.add_time
where a.history_id = ' . $history_id);
        if ($rs[0]) {
            $comments_info = array(
                'id' => $rs[0]['id'],
                'message' => $rs[0]['message'],
                'add_time' => $rs[0]['add_time'],
                'votes' => $rs[0]['votes']
            );
        } else {
            $comments_info = array();
        }
        return $comments_info;
    }

    public function update_article($article_id, $data)
    {
        $this->shutdown_update('article', $data, ' id = ' . intval($article_id));
        return true;
    }

    public function get_favorite_lists($uid, $limit = null)
    {
        $favorite_items = $this->fetch_all('favorite', "uid = " . intval($uid), 'item_id DESC', $limit);
        if (!$favorite_items) {
            return false;
        }

        foreach ($favorite_items as $key => $data) {
            if ($data['type'] == 'article') {
                $article_ids[] = $data['item_id'];
            } else {
                unset($favorite_items[$key]);
                continue;
            }
        }

        if ($article_ids) {
            if ($article_infos = $this->model('article')->get_article_info_by_ids($article_ids)) {
                foreach ($article_infos AS $key => $data) {
                    $favorite_uids[$data['uid']] = $data['uid'];
                }
            }
        }

        $users_info = $this->model('account')->get_user_info_by_uids($favorite_uids);

        foreach ($favorite_items as $key => $data) {
            if ($data['type'] == 'article') {
                $favorite_list_data[$key]['uid'] = $uid;
                $favorite_list_data[$key]['associate_action'] = 504;
                $favorite_list_data[$key]['title'] = $article_infos[$data['item_id']]['title'];
                $favorite_list_data[$key]['link'] = get_js_url('/article/' . $data['item_id']);
                $favorite_list_data[$key]['add_time'] = $favorite_items[$key]['time'];
                $favorite_list_data[$key]['user_info'] = $users_info[$article_infos[$data['item_id']]['uid']];
                $favorite_list_data[$key]['article_info'] = $article_infos[$data['item_id']];
                $favorite_list_data[$key]['last_action_str'] = ACTION_LOG::format_action_data(ACTION_LOG::ADD_ARTICLE, $data['uid'], $users_info[$data['uid']]['user_name']);

            }
        }
        return $favorite_list_data;
    }

    public function data_sort($data)
    {
        $cmp = function ($a, $b) {
            if ($a['add_time'] == $b['add_time']) {
                return 0;
            } else {
                return ($a['add_time'] < $b['add_time']) ? 1 : -1;
            }
        };
        usort($data, $cmp);
        return $data;
    }

    public function has_favorite_article($uid, $id)
    {
        $favorite = $this->fetch_all('favorite', "uid = " . intval($uid) . " and item_id = " . intval($id) . " and type = 'article'");
        if (!empty($favorite)) {
            return 1;
        } else {
            return 0;
        }
    }

    public function register_name_check($user_name)
    {
        if (is_digits($user_name)) {
            return 'a' . $user_name;
        }

//        $sensitives = array('-', '.', '/', '%', '__', '_');

//        $user_name = str_replace($sensitives, '', $user_name);
        $preg = '#(\\\ue[0-9a-f]{3})#ie';
        $boolPregRes = (preg_replace($preg, '', json_encode($user_name, true)));
        return trim(json_decode($boolPregRes));
    }
}
