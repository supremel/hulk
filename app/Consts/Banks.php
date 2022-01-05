<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-09
 * Time: 14:56
 */

namespace App\Consts;


class Banks
{
    public static function getIconByCode($code)
    {
        foreach (self::LIST as $item) {
            if ($item['code'] == $code) {
                return $item['icon'];
            }
        }
        return '';
    }

    const LIST = [
        [
            'code' => 'CMB',
            'name' => '招商银行',
            'icon' => 'ic_bank_zs.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'ICBC',
            'name' => '工商银行',
            'icon' => 'ic_bank_gs.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'CCB',
            'name' => '建设银行',
            'icon' => 'ic_bank_js.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'SPDB',
            'name' => '浦发银行',
            'icon' => 'ic_bank_pf.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'ABC',
            'name' => '农业银行',
            'icon' => 'ic_bank_ny.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'CIB',
            'name' => '兴业银行',
            'icon' => 'ic_bank_xy.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'CEB',
            'name' => '光大银行',
            'icon' => 'ic_bank_gd.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'BOC',
            'name' => '中国银行',
            'icon' => 'ic_bank_zg.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'PAB',
            'name' => '平安银行',
            'icon' => 'ic_bank_pa.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'GDB',
            'name' => '广发银行',
            'icon' => 'ic_bank_gf.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'PSBC',
            'name' => '邮政银行',
            'icon' => 'ic_bank_yc.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'CNCB',
            'name' => '中信银行',
            'icon' => 'ic_bank_zx.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'BOS',
            'name' => '上海银行',
            'icon' => 'ic_bank_sh.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'CMBC',
            'name' => '民生银行',
            'icon' => 'ic_bank_ms.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'HXB',
            'name' => '华夏银行',
            'icon' => 'ic_bank_hx.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'BOCOM',
            'name' => '交通银行',
            'icon' => 'ic_bank_jt.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'GZBC',
            'name' => '广州银行',
            'icon' => 'ic_bank_gz(1).png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'BRCB',
            'name' => '北京农商银行',
            'icon' => 'ic_bank_bjns.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'JSBC',
            'name' => '江苏银行',
            'icon' => 'ic_bank_js(1).png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'HZBC',
            'name' => '杭州银行',
            'icon' => 'ic_bank_hz.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'BGZC',
            'name' => '贵州银行',
            'icon' => 'ic_bank_gz(1).png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'EBCL',
            'name' => '恒丰银行',
            'icon' => 'ic_bank_hf.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'NBBC',
            'name' => '宁波银行',
            'icon' => 'ic_bank_nb.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
        [
            'code' => 'ZSBC',
            'name' => '浙商银行',
            'icon' => 'ic_bank_zhs.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
//        [
//            'code' => 'BOCO',
//            'name' => '交通银行',
//            'icon' => '',
//            'tag' => '',
//            'desc' => '借记卡',
//        ],
//        [
//            'code' => 'CMBCHINA',
//            'name' => '招商银行',
//            'icon' => '',
//            'tag' => '',
//            'desc' => '借记卡',
//        ],
//        [
//            'code' => 'SZPA',
//            'name' => '平安银行',
//            'icon' => '',
//            'tag' => '',
//            'desc' => '借记卡',
//        ],
//        ['code' => 'SHB',
//            'name' => '上海银行',
//            'icon' => '',
//            'tag' => '',
//            'desc' => '借记卡',
//        ],
        [
            'code' => 'BCCB',
            'name' => '北京银行',
            'icon' => 'ic_bank_bj.png',
            'tag' => '',
            'desc' => '借记卡',
        ],
    ];

    const CODE_NAME_MAPPINGS = [
        'CMB' => '招商银行',
        'ICBC' => '工商银行',
        'CCB' => '建设银行',
        'SPDB' => '浦发银行',
        'ABC' => '农业银行',
        'CIB' => '兴业银行',
        'CEB' => '光大银行',
        'BOC' => '中国银行',
        'PAB' => '平安银行',
        'GDB' => '广发银行',
        'PSBC' => '邮政银行',
        'CNCB' => '中信银行',
        'BOS' => '上海银行',
        'CMBC' => '民生银行',
        'HXB' => '华夏银行',
        'BOCOM' => '交通银行',
        'GZBC' => '广州银行',
        'BRCB' => '北京农商银行',
        'JSBC' => '江苏银行',
        'HZBC' => '杭州银行',
        'BGZC' => '贵州银行',
        'EBCL' => '恒丰银行',
        'NBBC' => '宁波银行',
        'ZSBC' => '浙商银行',
        'BOCO' => '交通银行',
        'CMBCHINA' => '招商银行',
        'SZPA' => '平安银行',
        'SHB' => '上海银行',
        'BCCB' => '北京银行',
    ];
}