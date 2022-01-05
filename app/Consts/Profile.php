<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-10
 * Time: 10:28
 */

namespace App\Consts;


class Profile
{
    const EDUCATIONS = [
        [
            'k' => '01',
            'v' => '高中及以下',
        ],
        [
            'k' => '02',
            'v' => '专科',
        ],
        [
            'k' => '03',
            'v' => '本科',
        ],
        [
            'k' => '04',
            'v' => '研究生及以上',
        ],
    ];

    const INDUSTRIES = [
        [
            'k' => '01',
            'v' => '农林牧渔从业人员',
        ],
        [
            'k' => '02',
            'v' => '普工、技工',
        ],
        [
            'k' => '03',
            'v' => '建筑工、装修工、维修工、物业',
        ],
        [
            'k' => '04',
            'v' => '厨师、服务员',
        ],
        [
            'k' => '05',
            'v' => '教师、培训师、教务管理',
        ],
        [
            'k' => '06',
            'v' => '医生、护士、药剂师',
        ],
        [
            'k' => '07',
            'v' => '公务员及企事业单位',
        ],
        [
            'k' => '08',
            'v' => '人事、行政、后勤、财务',
        ],
        [
            'k' => '09',
            'v' => '司机、快递员、外卖员、代驾',
        ],
        [
            'k' => '10',
            'v' => '销售、采购、客服',
        ],
        [
            'k' => '11',
            'v' => '美容美发、保健、健身教练',
        ],
        [
            'k' => '12',
            'v' => '工程师、设计师',
        ],
        [
            'k' => '13',
            'v' => '个体老板、淘宝店主等',
        ],
        [
            'k' => '14',
            'v' => '其他',
        ],
    ];

    const MONTH_INCOMES = [
        [
            'k' => '01',
            'v' => '3000以下',
        ],
        [
            'k' => '02',
            'v' => '3000-5000',
        ],
        [
            'k' => '03',
            'v' => '5000-8000',
        ],
        [
            'k' => '04',
            'v' => '8000-10000',
        ],
        [
            'k' => '05',
            'v' => '10000以上',
        ],
    ];

    const PURPOSES = [
        [
            "k" => "01",
            "v" => "日常消费",
        ],
        [
            "k" => "02",
            "v" => "教育",
        ],
        [
            "k" => "03",
            "v" => "装修",
        ],
        [
            "k" => "04",
            "v" => "旅游",
        ],
    ];

    const RELATIONSHIP_NUM = 4;
    const RELATIONSHIP_TYPE_FAMILY = 0;
    const RELATIONSHIP_TYPE_EMERGENCY = 1;
    const RELATIONSHIPS = [
        self::RELATIONSHIP_TYPE_FAMILY => [
            [
                "k" => "01",
                "v" => "父母",
            ],
            [
                "k" => "02",
                "v" => "配偶",
            ],
            [
                "k" => "05",
                "v" => "兄弟/姐妹",
            ],
        ],
        self::RELATIONSHIP_TYPE_EMERGENCY => [
            [
                "k" => "03",
                "v" => "朋友",
            ],
            [
                "k" => "04",
                "v" => "同事",
            ],
            [
                "k" => "06",
                "v" => "同学",
            ],
        ],
    ];

    public static function findValueByKey($data, $k)
    {
        foreach ($data as $rec) {
            if ($rec['k'] == $k) {
                return $rec['v'];
            }
        }
        return '';
    }


    // 认证类型（基本&补充）
    const AUTH_TYPE_REQUIRED = 0; // 基本（必选）
    const AUTH_TYPE_REQUIRED_THIRD = 2; //  基本-三方（必选）
    const AUTH_TYPE_OPTIONAL = 1; // 补充（可选）

    const AUTH_LIST = [
        self::AUTH_TYPE_REQUIRED => [
            Constant::DATA_TYPE_REAL_NAME => [
                'title' => '实名认证',
                'icon' => 'ic_real.png',
                'link' => Scheme::APP_USER_AUTH_REAL_NAME . '&show_bar=0',
                'statistics_id' => 'H03001',
            ],
            Constant::DATA_TYPE_BASE => [
                'title' => '个人/工作信息',
                'icon' => 'ic_me.png',
                'link' => Scheme::APP_USER_AUTH_BASE . '&show_bar=0',
                'statistics_id' => 'H03002',
            ],
            Constant::DATA_TYPE_RELATIONSHIP => [
                'title' => '紧急联系人',
                'icon' => 'ic_person.png',
                'link' => Scheme::APP_USER_AUTH_RELATIONSHIP . '&show_bar=0',
                'statistics_id' => 'H03003',
            ],
            Constant::DATA_TYPE_BANK => [
                'title' => '银行卡',
                'icon' => 'ic_card.png',
                'link' => Scheme::APP_USER_AUTH_BANK . '&show_bar=0',
                'statistics_id' => 'H03004',
            ],
            Constant::DATA_TYPE_PHONE => [
                'title' => '手机认证',
                'icon' => 'ic_phone.png',
                'link' => Scheme::APP_USER_TP_AUTH_PHONE,
                'statistics_id' => 'H03005',
            ],
//            Constant::DATA_TYPE_TAOBAO => [
//                'title' => '淘宝认证',
//                'icon' => 'ic_taobao.png',
//                'link' => Scheme::APP_USER_TP_AUTH_MOXIE,
//                'statistics_id' => 'H03006',
//            ],
        ],
        self::AUTH_TYPE_OPTIONAL => [
            Constant::DATA_TYPE_JD => [
                'title' => '京东',
                'icon' => 'ic_jd.png',
                'link' => Scheme::APP_BASE_SCHEME . '/h5/safe_webview?url=https%3a%2f%2fweb.ishuilian.com%2fsl%2fapp%2fcoming_soon%2f&title=%e6%95%ac%e8%af%b7%e6%9c%9f%e5%be%85',
                'statistics_id' => 'H03007',
            ],
            Constant::DATA_TYPE_MEITUAN => [
                'title' => '美团',
                'icon' => 'ic_mt.png',
                'link' => Scheme::APP_BASE_SCHEME . '/h5/safe_webview?url=https%3a%2f%2fweb.ishuilian.com%2fsl%2fapp%2fcoming_soon%2f&title=%e6%95%ac%e8%af%b7%e6%9c%9f%e5%be%85',
                'statistics_id' => 'H03008',
            ],
            Constant::DATA_TYPE_DIDI => [
                'title' => '滴滴',
                'icon' => 'ic_didi.png',
                'link' => Scheme::APP_BASE_SCHEME . '/h5/safe_webview?url=https%3a%2f%2fweb.ishuilian.com%2fsl%2fapp%2fcoming_soon%2f&title=%e6%95%ac%e8%af%b7%e6%9c%9f%e5%be%85',
                'statistics_id' => 'H03009',
            ],
        ],
        self::AUTH_TYPE_REQUIRED_THIRD => [
            Constant::DATA_TYPE_PHONE => [
                'title' => '手机认证',
                'icon' => 'ic_phone.png',
                'link' => Scheme::APP_USER_TP_AUTH_PHONE,
            ],
//            Constant::DATA_TYPE_TAOBAO => [
//                'title' => '淘宝认证',
//                'icon' => 'ic_taobao.png',
//                'link' => Scheme::APP_USER_TP_AUTH_MOXIE,
//            ],
        ],
    ];
}