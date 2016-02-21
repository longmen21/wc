<?php
if (!defined('IN_ANWSION'))
{
    die;
}

class famous extends AWS_CONTROLLER
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

    public function index_action() {

    }
}