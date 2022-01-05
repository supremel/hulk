<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-11
 * Time: 12:19
 */

namespace App\Helpers;


class Validator
{
    /**
     * 校验身份证号码中的生日编码
     * @param $birthCode
     * @return bool
     */
    private static function _checkBirthCodeOfIdentity($birthCode)
    {
        try {
            $date = strtotime($birthCode);
        } catch (\Exception $e) {
            return false;
        }
        if ($date) {
            return true;
        }
        return false;
    }

    /**
     * 校验身份证号码中的校验码
     * @param $identity
     * @param $validateCode
     * @return bool
     */
    private static function _checkValidateCodeOfIdentity($identity, $validateCode)
    {
        $index = 0;
        $sum = 0;
        while ($index < 17) {
            $item = ((1 << (17 - $index)) % 11) * (intval($identity[$index]));
            $sum = $sum + $item;
            $index = $index + 1;
        }

        $sum = (12 - ($sum % 11)) % 11;
        if ($sum < 10) {
            if ($validateCode == 'X') {
                return false;
            }
            return ($sum == intval($validateCode));
        } else {
            return strtoupper($validateCode) == 'X';
        }

    }

    /**
     * 校验身份证号码的合法性（地区码+出生日期码+顺序码+校验码）
     * @param $identity
     * @return bool
     */
    public static function validateIdentity($identity)
    {
        $l = strlen($identity);
        if ($l != 18) {
            return false;
        }
        if (0 === preg_match('/^[0-9]{17}[0-9xX]/', $identity)) {
            return false;
        }
        // 地址码
        $addrCode = substr($identity, 0, 6);


        // 出生日期码
        $birthCode = substr($identity, 6, 8);
        if (!self::_checkBirthCodeOfIdentity($birthCode)) {
            return false;
        }

        // 顺序码
        $serialCode = substr($identity, 14, 3);
        $sex = '男';
        if (intval($serialCode) % 2 == 0) {
            $sex = '女';
        }

        // 校验码
        $validateCode = substr($identity, 17, 1);
        if (!self::_checkValidateCodeOfIdentity($identity, $validateCode)) {
            return false;
        }
        return true;
    }

    /**
     * 校验中文姓名【2-10个中文字】
     * @param $name
     * @return bool
     */
    public static function validateChineseName($name)
    {
        if (preg_match("/^[\x{4e00}-\x{9fa5}\.]{2,10}$/u", $name)) {
            return true;
        }
        return false;
    }

}