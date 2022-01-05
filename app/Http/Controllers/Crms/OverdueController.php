<?php

namespace App\Http\Controllers\Crms;

use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Fee;
use App\Consts\Profile;
use App\Exceptions\CustomException;
use App\Http\Controllers\Controller;
use App\Models\AddrInfo;
use App\Models\BaseInfo;
use App\Models\IdCard;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\Relationship;
use App\Models\Users;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @Class   OverdueController
 * @Desc    crm贷后-逾期相关统计
 * @Author  liuhao
 * @Date    2019-08-21
 * @package App\Http\Controllers\Apis
 */
class OverdueController extends Controller
{

    /**
     * OverdueController constructor.
     * @Author            liuhao
     * @Date              2019-08-21
     */
    public function __construct()
    {
        //设置高精度计算精度
        bcscale(2);
    }

    /**
     * @desc   通过指定日期获取当日逾期订单
     * @action todayOverdueAction
     * @param  Request  $request
     * @return JsonResponse
     * @author liuhao
     * @date   2019-08-22
     */
    public function todayOverdue(Request $request)
    {
        //验证参数
        $validatedData = $request->validate(
            [
                'repayment_date' => 'integer',  //指定日期的时间戳
            ]
        );

        //判空参数,默认为今日零时
        $date = date('Y-m-d', strtotime('yesterday'));
        if (!empty($validatedData['repayment_date'])) {
            $date = date('Y-m-d', Utils::millisecondToSecond($validatedData['repayment_date']));
        }

        //读取订单分期数据库,order_installments
        $orderInstallmentsModel = new OrderInstallments();
        $orderInstallments      = $orderInstallmentsModel->getInstallmentOverdueWaitByDate($date);

        //没有读取到数据直接返回初始化数据
        $resultData = ['overdueOrderList' => []];
        if (empty($orderInstallments)) {
            return $this->render($resultData);
        }

        //获取未还款订单的用户UID和orderID
        $userIdList  = [];
        $orderIdList = [];
        foreach ($orderInstallments as $key => $value) {
            if (!in_array($value['user_id'], $userIdList)) {
                $userIdList[] = $value['user_id'];
            }
            if (!in_array($value['order_id'], $orderIdList)) {
                $orderIdList[] = $value['order_id'];
            }
        }

        //通过获取到的UID列表读取用户信息
        $usersModel   = new Users();
        $userInfoList = $usersModel->getInfosByIdList($userIdList);
        if (empty($userInfoList)) {
            return $this->render($resultData);
        }
        $userInfoList = Utils::arrayRebuild($userInfoList, 'id');

        //通过获取到的orderID列表读取订单信息
        $ordersModel   = new Orders();
        $orderInfoList = $ordersModel->getInfosByIdList($orderIdList);
        if (empty($orderInfoList)) {
            return $this->render($resultData);
        }
        $orderInfoList = Utils::arrayRebuild($orderInfoList, 'id');

        //最后拼装响应数据
        foreach ($orderInstallments as $key => $value) {
            $tmp                  = [];
            $userId               = $value['user_id'];
            $tmp['channelName']   = Constant::USER_SOURCE_DICT[$orderInfoList[$value['order_id']]['source']] ?? '';  //借贷渠道名称
            $tmp['mobile']        = empty($userInfoList[$userId]) ? '' : $userInfoList[$userId]['phone'];    //手机号
            $tmp['userName']      = empty($userInfoList[$userId]) ? '' : $userInfoList[$userId]['name'] ?? ''; //用户姓名
            $tmp['nowPeriods']    = $value['period'];   //当前期数
            $paymentAmount        = bcadd($value['capital'], $value['interest']);
            $paymentAmount        = bcadd($paymentAmount, $value['fee']);
            $tmp['paymentAmount'] = $this->convertPennyToYuan($paymentAmount);  //账期总金额
            $tmp['repaymentDate'] = Utils::secondToMillisecond(strtotime($value['date']));     //应还款日
            $tmp['orderSn']       = $orderInfoList[$value['order_id']]['biz_no']; //订单号
            $tmp['totalPeriods']  = $orderInfoList[$value['order_id']]['periods']; //总期数
            //赋值
            $resultData['overdueOrderList'][$key] = $tmp;
        }
        return $this->render($resultData);
    }

    /**
     * @desc   将字符串分为单位的转换成元
     * @action convertPennyToYuanAction
     * @param  string  $penny
     * @return string
     * @author liuhao
     * @date   2019/8/29
     */
    public function convertPennyToYuan($penny)
    {
        $result = $penny / 100;

        return $result;
    }

    /**
     * @desc   通过用户ID获取用户今日逾期订单
     * @action userOverdueAction
     * @param  Request  $request
     * @return JsonResponse
     * @author liuhao
     * @date   2019-08-26
     */
    public function userOverdue(Request $request)
    {
        //验证参数
        $validatedData = $request->validate(
            [
                //integer
                'order_sn' => 'required|numeric',  //订单流水号
            ]
        );
        $bizNumber     = $validatedData['order_sn']; //流水号

        //读取订单信息
        $ordersModel = new Orders();
        $orderInfo   = $ordersModel->getInfoByBizNo($bizNumber);
        //订单不存在,直接返回
        if (empty($orderInfo)) {
            return $this->render([]);
        }

        //通过订单信息获取用户信息
        $usersModel = new Users();
        $userInfo   = $usersModel->getInfoById($orderInfo['user_id']);

        //获取用户紧急联系人等信息
        $relationshipModel = new Relationship();
        $userRelationship  = $relationshipModel->getSuccessListByUserId($orderInfo['user_id']);

        $userRelationshipFamily  = [];   //直系亲属
        $userRelationshipUrgency = [];  //紧急联系人
        foreach ($userRelationship as $value) {
            if ($value['type'] == Profile::RELATIONSHIP_TYPE_FAMILY) {
                $userRelationshipFamily = $value;
            } else {
                $userRelationshipUrgency = $value;
            }
        }

        //获取用户居住地等信息
        $baseInfoModel = new BaseInfo();
        $userBaseInfo  = $baseInfoModel->getInfoByUserId($orderInfo['user_id']);

        //处理用户与各种联系人之间的关系
        $relationFamilyMap  = Profile::RELATIONSHIPS[Profile::RELATIONSHIP_TYPE_FAMILY];    //直系联系人映射关系
        $relationUrgencyMap = Profile::RELATIONSHIPS[Profile::RELATIONSHIP_TYPE_EMERGENCY]; //紧急联系人映射关系

        $userFamilyRelationship  = '';
        $userUrgencyRelationship = '';
        if (!empty($userRelationshipFamily)) {
            foreach ($relationFamilyMap as $value) {
                if ($value['k'] == $userRelationshipFamily['relation']) {
                    $userFamilyRelationship = $value['v'];
                }
            }
        }
        if (!empty($userRelationshipUrgency)) {
            foreach ($relationUrgencyMap as $value) {
                if ($value['k'] == $userRelationshipUrgency['relation']) {
                    $userUrgencyRelationship = $value['v'];
                }
            }
        }

        //获取平台支持的地址
        $addrJoint = [];
        if (!empty($userBaseInfo)) {
            $addrCodeList   = [];
            $addrCodeList[] = $userBaseInfo['county'] ?? '';
            $addrCodeList[] = $userBaseInfo['province'] ?? '';
            $addrCodeList[] = $userBaseInfo['city'] ?? '';
            $addrModel      = new AddrInfo();
            $addrInfo       = $addrModel->getAddrInfoByCode($addrCodeList);
            foreach ($addrInfo as $value) {
                if ($value['province'] == 0 && $value['city'] == 0) {
                    $addrJoint[0] = $value['name'];
                } elseif ($value['city'] == 0 && $value != 0) {
                    $addrJoint[1] = $value['name'];
                } else {
                    $addrJoint[2] = $value['name'];
                }
            }
        }

        //获取用户实名认证信息
        $addr = '';
        $age  = '';
        $sex  = '';
        $idCardModel    = new IdCard();
        $userIdCardInfo = $idCardModel->getSuccessInfoByUserId($orderInfo['user_id']);
        if (!empty($userIdCardInfo)) {
            $addr = join(',', $addrJoint);
            $age  = strval($userIdCardInfo['age']);
            $sex  = Constant::GENDER_LIST[$userIdCardInfo['gender']];
        }

        //拼装数据
        $resultData             = ['userInfo' => []];
        $resultData['userInfo'] = [
            'address'                  => $userIdCardInfo['addr'] ?? '',      // 户籍地址
            'age'                      => $age,      // 年龄
            'sex'                      => $sex,// 性别
            'realName'                 => $userIdCardInfo['name'] ?? '',      // 真实姓名
            'idCard'                   => $userIdCardInfo['identity'] ?? '',      // 身份证号码
            'trueContactMobile'        => $userRelationshipFamily['phone'] ?? '',      // 直系联系人电话
            'trueContactName'          => $userRelationshipFamily['name'] ?? '',      // 直系联系人关系
            'trueContactRelation'      => $userFamilyRelationship,      // 直系联系人关系
            'emergencyContactMobile'   => $userRelationshipUrgency['phone'] ?? '',      // 紧急联系人电话
            'emergencyContactName'     => $userRelationshipUrgency['name'] ?? '',      // 紧急联系人姓名
            'emergencyContactRelation' => $userUrgencyRelationship,      // 紧急联系人关系
            'liveCity'                 => $addr,      // 省市县地址
            'liveCityDetail'           => $userBaseInfo['addr'] ?? '',      // 居住地址
            'companyAddr'              => '',      // 公司地址
            'companyName'              => $userBaseInfo['company_name'] ?? '',      // 公司名称
            'mobile'                   => $userInfo['phone'] ?? '',      // 手机号
            'userId'                   => $userInfo['old_user_id'],        // 用户ID,要求置为0. 贷后王宽要求使用old_user_id
            'oldUserId'                => $userInfo['old_user_id'],      // 老用户ID
            'uuid'                     => $userInfo['uid'],      // 新用户ID
            'orderSn'                  => $orderInfo['biz_no'],      // 订单号
        ];

        return $this->render($resultData);
    }

    /**
     * @desc   根据订单号获取当前订单逾期状态
     * @action overdueOrderStatusAction
     * @param  Request  $request
     * @return JsonResponse
     * @author liuhao
     * @date   2019-08-22
     */
    public function overdueOrderStatus(Request $request)
    {
        //验证参数
        $validatedData = $request->validate(
            [
                //integer
                'order_sn' => 'required|numeric',  //订单流水号
            ]
        );
        $bizNumber     = $validatedData['order_sn']; //流水号

        //查询订单数据
        $ordersModel = new Orders();
        $orderInfo   = $ordersModel->getInfoByBizNoAndStatus($bizNumber, Constant::ORDER_STATUS_LOAN_SUCCESS);
        $resultData  = ['overdueOrderList' => []];
        if (empty($orderInfo)) {
            return $this->render($resultData);
        }

        //获取该订单的分期情况中,最早的此逾期情况
        $orderInstallmentsModel = new OrderInstallments();
        $orderFirstOverdue      = $orderInstallmentsModel->getInstallmentOverdueWaitByOrderId($orderInfo['id']);
        if (empty($orderFirstOverdue)) {
            return $this->render($resultData);
        }

        //获取个人信息
        $usersModel = new Users();
        $userInfo   = $usersModel->getInfoById($orderInfo['user_id']);

        //组装数据,返回
        $paymentAmount                    = $this->getDebtByOrderInstallmentsInfo($orderFirstOverdue);
        $resultData['overdueOrderList'][] = [
            'nowPeriods'    => $orderFirstOverdue['period'],        //当前逾期期数
            'orderSn'       => $bizNumber,        //订单号,即流水号
            'paymentAmount' => $this->convertPennyToYuan($paymentAmount),        //应还金额
            'productName'   => '',        //产品名称,无
            'repaymentDate' => Utils::secondToMillisecond(strtotime($orderFirstOverdue['date'])),        //应还款日
            'totalPeriods'  => $orderInfo['periods'],        //账期总期数
            'userName'      => $userInfo['name'] ?? '',        //用户姓名
        ];

        return $this->render($resultData);
    }

    /**
     * @desc   获取剩余应还金额
     * @action getDebtByOrderInstallmentsInfoAction
     * @param  array  $value
     * @return string
     * @author liuhao
     * @date   2019-08-22
     */
    private function getDebtByOrderInstallmentsInfo(array $value)
    {
        //所有欠款总和,包括利息和逾期费,其他
        $total = bcadd($value['capital'], $value['interest']);
        $total = bcadd($total, $value['fee']);
        $total = bcadd($total, $value['other_fee']);
        //所有已还欠款总和,包括利息和逾期费,其他
        $repaid = bcadd($value['paid_capital'], $value['paid_interest']);
        $repaid = bcadd($repaid, $value['paid_fee']);
        $repaid = bcadd($repaid, $value['paid_other_fee']);
        //求差,获得剩余应还数量
        $debt = bcsub($total, $repaid);
        return $debt;
    }

    /**
     * @desc   根据订单号获取当前订单还款计划详情
     * @action overdueOrderPlanAction
     * @param  Request  $request
     * @return JsonResponse
     * @author liuhao
     * @date   2019-08-22
     */
    public function overdueOrderPlan(Request $request)
    {
        //验证参数
        $validatedData = $request->validate(
            [
                //integer
                'order_sn' => 'required|numeric',  //订单流水号
            ]
        );
        $bizNumber     = $validatedData['order_sn']; //流水号
        //查询订单数据
        $ordersModel = new Orders();
        $orderInfo   = $ordersModel->getInfoByBizNo($bizNumber);
        $resultData  = ['creditRepaymentInfoList' => []];
        if (empty($orderInfo)) {
            return $this->render($resultData);
        }

        //查询订单分期信息
        $orderInstallmentsModel = new OrderInstallments();
        $orderInstallmentsList  = $orderInstallmentsModel->getInstallmentListByOrderId($orderInfo['id']);
        if (empty($orderInstallmentsList)) {
            return $this->render($resultData);
        }

        //处理结果,返回数据
        foreach ($orderInstallmentsList as $key => $value) {
            $tmp['overdueInterest'] = $this->convertPennyToYuan($value['fee']);//逾期罚息
            $tmp['orderSn']         = $bizNumber;//订单号,即订单流水号
            //所有已还欠款总和,包括利息和逾期费
            $repaid                     = bcadd($value['capital'], $value['interest']);
            $totalMoneyAll              = bcadd($repaid, $value['other_fee']);
            $totalMoneyAll              = bcadd($totalMoneyAll, $value['fee']);
            $tmp['totalMoneyAll']       = $this->convertPennyToYuan($totalMoneyAll);//月还金额, todo应该是应还本金+应还利息+其他
            $amountRepayment            = $this->getTotalAlreadyRepay($value);
            $amountRepayment            = $this->convertPennyToYuan($amountRepayment);
            $tmp['amountRepayment']     = $amountRepayment;//已还总金额
            $partialRepaymentSum        = $value['status'] == Constant::ORDER_INTEREST_STATUS_DONE ? '0' : $amountRepayment;
            $tmp['partialRepaymentSum'] = $partialRepaymentSum;//部分还款金额,当前分期下的总还款金额,已还完时显示'0'
            $tmp['num']                 = $value['period'];//期数
            $totalMoney                 = $this->getDebtByOrderInstallmentsInfo($value);
            $tmp['totalMoney']          = $this->convertPennyToYuan($totalMoney);//应还款金额 todo应该是应还本金+应还利息+违约金+其他
            $tmp['planDate']            = $value['date'];//理论还款日”YYYY-mm-dd”
            $tmp['planPrincipal']       = $this->convertPennyToYuan($value['capital']);//应还本金
            $tmp['repaymentDate']       = Utils::secondToMillisecond(strtotime($value['date']));//理论还款日(时间戳，例：1560182400000）
            $tmp['overdueDays']         = $value['overdue_days'];//逾期天数
            $tmp['serviceMoney']        = $this->convertPennyToYuan($value['interest']);//应还服务费,就是应还利息
            $tmp['prepaymentPenalty']   = '0';//提前还款违约金总数
            $tmp['secondPrincipal']     = '0';//应还分期会员费总数
            $tmp['memberInterest']      = '0';//应还会员费利息总数
            if ($value['other_fee_type'] == Fee::OTHER_FEE_TYPE_PREPAY_FEE) {
                //提前还款手续费
                $tmp['prepaymentPenalty'] = $this->convertPennyToYuan($value['other_fee']);
            } elseif ($value['other_fee_type'] == Fee::OTHER_FEE_TYPE_MEMBER_FEE) {
                // 会员费（砍头失败）
                $tmp['secondPrincipal'] = $this->convertPennyToYuan($value['other_fee_capital']);
                $tmp['memberInterest']  = $this->convertPennyToYuan(bcsub(
                    $value['other_fee'],
                    $value['other_fee_capital']
                ));
            }
            $tmp['realDate'] = $value['pay_off_time'] ?? 0;//实还日期
            $tmp['status']   = $this->getZhStatusByOrderInstallmentsInfo($value);
            //赋值
            $resultData['creditRepaymentInfoList'][$key] = $tmp;
        }

        return $this->render($resultData);
    }

    /**
     * @desc   获取已还总额
     * @action getTotalAlreadyRepay
     * @param  array  $value
     * @return string
     * @author liuhao
     * @date   2019-08-26
     */
    private function getTotalAlreadyRepay(array $value)
    {
        //所有已还欠款总和,包括利息和逾期费
        $repaid = bcadd($value['paid_capital'], $value['paid_interest']);
        $repaid = bcadd($repaid, $value['paid_fee']);
        $repaid = bcadd($repaid, $value['paid_other_fee']);

        return $repaid;
    }

    /**
     * @desc   通过还款计划详情,获取该分期详情的中文状态
     * @action getZhStatusByOrderInstallmentsInfoAction
     * @param  array  $orderInstallmentsInfo
     * @return string
     * @author liuhao
     * @date   2019-08-22
     */
    private function getZhStatusByOrderInstallmentsInfo(array $orderInstallmentsInfo)
    {
        //状态,（ ‘逾期已还’ 、’逾期未还’、 ‘正常已还’、’提前还款’、 ‘正常未还’ 、’未知状态’)
        $status = '';
        if ($orderInstallmentsInfo['overdue_days'] > 0) {
            //已逾期
            $status = '逾期已还';
            if ($orderInstallmentsInfo['status'] == Constant::ORDER_INTEREST_STATUS_WAIT) {
                $status = '逾期未还';
            }
        } else {
            //正常还款,提前还款,还未到期
            $status = '正常已还';
            if ($orderInstallmentsInfo['status'] == Constant::ORDER_INTEREST_STATUS_WAIT) {
                $status = '正常未还';
            } else {
                if (strtotime($orderInstallmentsInfo['pay_off_time']) < strtotime($orderInstallmentsInfo['date'])) {
                    $status = '提前还款';
                }
            }
        }
        return $status;
    }

    /**
     * @desc   根据订,单号列表,获取总逾期金额
     * @action overdueOrderTotalAmountAction
     * @param  Request  $request
     * @return JsonResponse
     * @throws CustomException
     * @author liuhao
     * @date   2019-08-23
     */
    public function overdueOrderTotalAmount(Request $request)
    {
        //验证参数,限制数据长度
        $validatedData = $request->validate(
            [
                'order_sns' => 'required|regex:/[0-9]+[,]?/',  //订单流水号
            ]
        );
        $bizNumber     = $validatedData['order_sns']; //流水号
        $bizNumberList = explode(',', $bizNumber);
        $bizNumberList = array_unique($bizNumberList);
        if (count($bizNumberList) > 100) {
            throw new CustomException(
                ErrorCode::COMMON_PARAM_ERROR,
                ErrorCode::CODE_MSG_DICT[ErrorCode::COMMON_PARAM_ERROR]
            );
        }

        //查询订单数据
        $ordersModel = new Orders();
        $orderList   = $ordersModel->getListByBizNoListAndStatus($bizNumberList, Constant::ORDER_STATUS_LOAN_SUCCESS);
        $resultData  = ['totalMoney' => '0'];
        if (empty($orderList)) {
            return $this->render($resultData);
        }
        $orderList = Utils::arrayRebuild($orderList, 'id');

        //根据获得的orderId所有Order的总欠款
        $orderInstallmentsModel = new OrderInstallments();
        $sum                    = $orderInstallmentsModel->getSumDebtByOrderIdList(array_keys($orderList));
        $resultData             = ['totalMoney' => $this->convertPennyToYuan($sum)];

        return $this->render($resultData);
    }

    /**
     * @desc   总共应还款金额
     * @action getTotalNeedRepay
     * @param  array  $value
     * @return string
     * @author liuhao
     * @date   2019/8/29
     */
    private function getTotalNeedRepay(array $value)
    {
        //所有已还欠款总和,包括利息和逾期费
        $repaid = bcadd($value['capital'], $value['interest']);
        $repaid = bcadd($repaid, $value['fee']);
        $repaid = bcadd($repaid, $value['other_fee']);

        return $repaid;
    }
}
