<?php
/**
 * 费率相关常量
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-12
 * Time: 11:50
 */

namespace App\Consts;

class Fee
{
    const OTHER_FEE_TYPE_NONE = 0;
    const OTHER_FEE_TYPE_PREPAY_FEE = 1; // 提前还款手续费
    const OTHER_FEE_TYPE_MEMBER_FEE = 2; // 会员费（砍头失败）
    const CAPITAL_FEE_DICT = [
        Constant::BORROW_PERIODS_6 => self::CAPITAL_FEE_6,
        Constant::BORROW_PERIODS_9 => self::CAPITAL_FEE_9,
        Constant::BORROW_PERIODS_12 => self::CAPITAL_FEE_12,
    ];

    const CAPITAL_FEE_6 = [
        1 => [
            'divisor' => 2, // 除数
            'rate' => 0.40, // 占比
        ],
        2 => [
            'divisor' => 2,
            'rate' => 0.40,
        ],
        3 => [
            'divisor' => 4,
            'rate' => 0.60,
        ],
        4 => [
            'divisor' => 4,
            'rate' => 0.60,
        ],
        5 => [
            'divisor' => 4,
            'rate' => 0.60,
        ],
        6 => [
            'divisor' => 4,
            'rate' => 0.60,
        ],
    ];


    const CAPITAL_FEE_9 = [
        1 => [
            'divisor' => 3,
            'rate' => 0.42,
        ],
        2 => [
            'divisor' => 3,
            'rate' => 0.42,
        ],
        3 => [
            'divisor' => 3,
            'rate' => 0.42,
        ],
        4 => [
            'divisor' => 6,
            'rate' => 0.58,
        ],
        5 => [
            'divisor' => 6,
            'rate' => 0.58,
        ],
        6 => [
            'divisor' => 6,
            'rate' => 0.58,
        ],
        7 => [
            'divisor' => 6,
            'rate' => 0.58,
        ],
        8 => [
            'divisor' => 6,
            'rate' => 0.58,
        ],
        9 => [
            'divisor' => 6,
            'rate' => 0.58,
        ],
    ];
    const CAPITAL_FEE_12 = [
        1 => [
            'divisor' => 1,
            'rate' => 0.14,
        ],
        2 => [
            'divisor' => 2,
            'rate' => 0.25,
        ],
        3 => [
            'divisor' => 2,
            'rate' => 0.25,
        ],
        4 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        5 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        6 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        7 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        8 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        9 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        10 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        11 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
        12 => [
            'divisor' => 9,
            'rate' => 0.61,
        ],
    ];
}