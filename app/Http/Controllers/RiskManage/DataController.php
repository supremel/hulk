<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-18
 * Time: 14:24
 */

namespace App\Http\Controllers\RiskManage;

use App\Common\OssClient;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Profile;
use App\Exceptions\CustomException;
use App\Http\Controllers\Controller;
use App\Models\AddrInfo;
use App\Models\AuthInfo;
use App\Models\BankCard;
use App\Models\BaseInfo;
use App\Models\DeviceInfo;
use App\Models\EventRecords;
use App\Models\IdCard;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\Procedures;
use App\Models\Relationship;
use App\Models\RiskEvaluations;
use App\Models\Users;
use App\Services\HulkEventService;
use Illuminate\Http\Request;

class DataController extends Controller
{
    const PRODUCT = 2000;

    const USER_TYPE_NEW = 0; // 在该产品线之前没有过订单
    const USER_TYPE_LOANED = 1; // 在该产品线之前有过放款的订单
    const USER_TYPE_AGAIN = 2; // 之前有订单，且一次都没放款

    const CHANNEL_DICT = [
        Constant::USER_SOURCE_APP
    ];
    const ORDER_STATUS_DICT = [
        Constant::ORDER_STATUS_INIT => Constant::ORDER_STATUS_INIT,
        Constant::ORDER_STATUS_LOAN_FAILED => 1000, // 放款失败
        Constant::ORDER_STATUS_AUDIT => 500, // 审核中
        Constant::ORDER_STATUS_LOAN => Constant::ORDER_STATUS_LOAN, // 放款中
        Constant::ORDER_STATUS_WITHDRAW => Constant::ORDER_STATUS_WITHDRAW, // 提现中
        Constant::ORDER_STATUS_LOAN_SUCCESS => 900, // 放款成功
        Constant::ORDER_STATUS_PAID_OFF => 1100, // 已结清
    ];

    const RELATION_DICT = [
        '01' => 0,
        '02' => 1,
        '03' => 6,
        '04' => 4,
        '05' => 5,
        '06' => 7,
    ];

    /**
     * @param $userInfo 用户信息
     * @param $baseInfo 基础信息
     * @param $relationShips 关系信息
     * @param $idCard 身份证信息
     * @param $bankCard 银行卡信息
     * @param $orders 订单信息
     * @return array
     */
    private function _formatProfileData($userInfo, $baseInfo, $relationShips, $idCard, $bankCard, $orders)
    {
        $contacts = [];
        foreach ($relationShips as $relationShip) {
            $contacts[] = [
                'phone' => $relationShip['phone'],
                'name' => $relationShip['name'],
                'relationship' => self::RELATION_DICT[$relationShip['relation']],
                'index' => $relationShip['type'] + 1,
            ];
        }

        $province = '';
        $city = '';
        $region = '';
        if (!empty($baseInfo['province'])) {
            $addrData = AddrInfo::whereIn('code', [$baseInfo['province'],
                $baseInfo['city'], $baseInfo['county']])->get()->toArray();
            foreach ($addrData as $addr) {
                if ($addr['code'] == $baseInfo['province']) {
                    $province = $addr['name'];
                } else if ($addr['code'] == $baseInfo['city']) {
                    $city = $addr['name'];
                } else {
                    $region = $addr['name'];
                }
            }
        }
        $faceData = AuthInfo::where('user_id', $userInfo['id'])->where('type', Constant::DATA_TYPE_FACE)
            ->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
        $faceItems = explode(';', $faceData['extra']);
        $face = [];
        foreach ($faceItems as $faceItem) {
            $face[] = OssClient::getUrlByFilename(Constant::FILE_TYPE_FACE, $faceItem);
        }
        $data = [
            'old_user_id' => $userInfo['old_user_id'],
            'uid' => $userInfo['uid'],
            'name' => $userInfo['name'],
            'identity' => $userInfo['identity'],
            'idcard_start_date' => $idCard['start_time'],
            'idcard_end_date' => $idCard['end_time'],
            'changed_time' => strtotime($userInfo['updated_at']),
            'identity_photo_front' => OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_FRONT, $idCard['front_id']),
            'identity_photo_back' => OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_BACK, $idCard['back_id']),
            'face' => $face,
            'education' => $baseInfo ? Profile::findValueByKey(Profile::EDUCATIONS, $baseInfo['education']) : '',
            'income' => $baseInfo ? Profile::findValueByKey(Profile::MONTH_INCOMES, $baseInfo['month_income']) : '',
            'industry' => $baseInfo ? Profile::findValueByKey(Profile::INDUSTRIES, $baseInfo['industry']) : '',
            'profession' => '',
            'bank_card_num' => $bankCard ? $bankCard['card_no'] : '',
            'bank_card_phone' => $bankCard ? $bankCard['reserved_phone'] : '',
            'bank_card_province' => '',
            'bank_card_city' => '',
            'bank_card_org' => $bankCard ? $bankCard['bank_code'] : '',
            'company_province' => $province,
            'company_city' => $city,
            'company_region' => $region,
            'company_address' => $baseInfo ? $baseInfo['addr'] : '',
            'company_name' => $baseInfo ? $baseInfo['company_name'] : '',
            'urgent_contacts' => $contacts,
        ];
        return $data;
    }

    /**
     * @param $userId
     * @return int
     */
    private function _calcUserType($userId)
    {
        $userType = self::USER_TYPE_NEW;
        $orders = Orders::where('user_id', $userId)->get()->toArray();
        if ($orders) {
            $userType = self::USER_TYPE_AGAIN;
            foreach ($orders as $order) {
                $status = $order['status'];
                if (in_array($status, [Constant::ORDER_STATUS_LOAN_SUCCESS, Constant::ORDER_STATUS_PAID_OFF])) {
                    $userType = self::USER_TYPE_LOANED;
                    break;
                }
            }

        }
        return $userType;
    }

    public function creditDetail(Request $request)
    {
        $validatedData = $request->validate(
            [
                'application_id' => 'required',
            ]
        );
        $bizNo = $validatedData['application_id'];
        $riskRecord = RiskEvaluations::where('biz_no', $bizNo)->first();
        if (!$riskRecord) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '授信id错误');
        }
        $userId = $riskRecord['user_id'];
        $userInfo = Users::where('id', $userId)->first();
        $deviceInfo = DeviceInfo::where('user_id', $userId)->first();
        $orderId = '';
        $loanAmount = 0;
        $procedureInfo = Procedures::where('id', $riskRecord['procedure_id'])->first();
        $periods = -1;
        if ($riskRecord['num'] == 2) {
            $order = Orders::where('id', $procedureInfo['order_id'])->first();
            $orderId = $order['biz_no'];
            $loanAmount = $procedureInfo['order_amount'];
            $periods = $procedureInfo['order_periods'];
        }
        $idCard = IdCard::where('user_id', $userId)->where('status', Constant::AUTH_STATUS_SUCCESS)
            ->first();
        $userType = $this->_calcUserType($userId);
        $eventInfo = EventRecords::where('relation_id', $riskRecord['id'])
            ->where('type', HulkEventService::EVENT_TYPE_RISK_EVALUATION_CREATION)->first();
        $isLoaned = 0;
        $lastIsRejected = 0;
        if ($eventInfo) {
            $data = json_decode($eventInfo['data'], true);
            $isLoaned = $data['is_loaned'];
            $lastIsRejected = ($data['last_is_rejected'] == -1) ? 0 : $data['last_is_rejected'];
        }
        $data = [
            'application_id' => $bizNo,
            'source' => $riskRecord['num'],
            'order_id' => $orderId,
            'old_user_id' => $userInfo['old_user_id'],
            'uid' => $userInfo['uid'],
            'product' => self::PRODUCT,
            'register_channel' => $userInfo['reg_channel'],
            'apply_channel' => $procedureInfo['source'] ?? '',
            'is_loaned' => $isLoaned,
            'last_is_rejected' => $lastIsRejected,
            'user_type' => $userType,
            'loan_amount' => $loanAmount,
            'credit_amount' => $riskRecord['amount'] ?? 0,
            'credit_time' => strtotime($riskRecord['finish_time']),
            'created_time' => strtotime($riskRecord['request_time']),
            'user_created_time' => strtotime($userInfo['created_at']),
            'periods' => $periods,
            'identity' => $userInfo['identity'],
            'phone' => $userInfo['phone'],
            'reg_phone' => $userInfo['phone'],
            'sex' => $idCard ? ($idCard['gender'] == Constant::GENDER_MEN ? '男' : '女') : '',
            'name' => $userInfo['name'],
            'age' => $idCard ? $idCard['age'] : 0,
            'identity_location' => $idCard ? $idCard['addr'] : '',
            'identity_birth' => $idCard ? $idCard['birthday'] : '',
            'app_version' => $deviceInfo['version'],
        ];
        return $this->render($data);
    }

    public function profile(Request $request)
    {
        $uid = $request->input('uid');
        $oldUserId = $request->input('old_user_id');
        if (!empty($uid)) {
            $userInfo = Users::where('uid', $uid)->first();
        } elseif (!empty($oldUserId)) {
            $userInfo = Users::where('old_user_id', $oldUserId)->first();
        } else {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR);
        }
        if (!$userInfo) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '用户id错误');
        }
        $userId = $userInfo['id'];
        $baseInfo = BaseInfo::where('user_id', $userId)->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
        $relationShips = Relationship::where('user_id', $userId)->where('status', Constant::AUTH_STATUS_SUCCESS)
            ->get()->toArray();
        $idCard = IdCard::where('user_id', $userId)->where('status', Constant::AUTH_STATUS_SUCCESS)
            ->first();
        $bankCard = BankCard::where('user_id', $userId)->where('status', Constant::AUTH_STATUS_SUCCESS)
            ->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
            ->first();
        $orders = Orders::where('user_id', $userId)->get()->toArray();
        $data = $this->_formatProfileData($userInfo, $baseInfo, $relationShips, $idCard, $bankCard, $orders);
        return $this->render($data);
    }

    public function relationship(Request $request)
    {
        $validatedData = $request->validate(
            [
                'emergency_contact_mobile' => 'required|size:11',
            ]
        );
        $phone = $validatedData['emergency_contact_mobile'];
        $relations = Relationship::where('phone', $phone)->where('status', Constant::AUTH_STATUS_SUCCESS)->get()->toArray();
        $contacts = [];
        foreach ($relations as $relation) {
            $userInfo = Users::where('id', $relation['user_id'])->first();
            if (!$userInfo) {
                continue;
            }
            $contacts[] = [
                'old_user_id' => $userInfo['old_user_id'],
                'uid' => $userInfo['uid'],
                'mobile' => $userInfo['phone'],
                'relation_type' => $relation['type'] + 1,
                'relation_ship' => self::RELATION_DICT[$relation['relation']],
            ];
        }
        $data = [
            'emergency_contact_same' => $contacts,
        ];
        return $this->render($data);
    }

    public function creditHistory(Request $request)
    {
        $validatedData = $request->validate(
            [
                'mobile' => 'required|size:11',
            ]
        );
        $phone = $validatedData['mobile'];
        $userInfo = Users::where('phone', $phone)->first();
        if (!$userInfo) {
            throw new CustomException(ErrorCode::USER_NOT_EXISTED);
        }
        $riskRecord = RiskEvaluations::where('user_id', $userInfo['id'])->orderBy('id', 'desc')->first();
        $riskBizNo = '';
        if ($riskRecord) {
            $riskBizNo = $riskRecord['biz_no'];
        }
        $creditInfos = [];
        $records = RiskEvaluations::where('user_id', $userInfo['id'])->get()->toArray();
        foreach ($records as $record) {
            $account = 0;
            $orderSn = '';
            if ($record['num'] == Constant::RISK_EVALUATION_INDEX_TWO) {
                $order = Orders::where('procedure_id', $record['procedure_id'])->first();
                if ($order) {
                    $account = $order['amount'];
                    $orderSn = $order['biz_no'];
                }
            }
            $creditInfos[] = [
                'date_unit' => 2,
                'account' => $account,
                'order_sn' => $orderSn,
                'application_id' => $record['biz_no'],
                'credit_type' => $record['num'] - 1,
                'source' => $record['num'],
                'credit_periods' => $record['cate'],
                'product' => self::PRODUCT,
                'product_channel' => 'app',
                'register_channel' => $userInfo['reg_channel'],
                'created_time' => strtotime($record['created_at']),
                'suggestion' => $record['status'],
                'final_score' => $record['score'],
                'fee_rate' => $record['fee_rate'] / 10000.0,
                'vendor' => $record['vendor'],
                'repay_type' => $record['repay_type'],
                'fee_type' => $record['fee_type'],
                'unvalid_list' => $record['unvalid_list'],
                'rejected_time' => $record['freeze'] * 24 * 3600,
            ];
        }
        $data = [
            'application_id' => $riskBizNo,
            'old_user_id' => $userInfo['old_user_id'],
            'uid' => $userInfo['uid'],
            'order_history' => $creditInfos,
        ];
        return $this->render($data);
    }

    public function orderHistory(Request $request)
    {
        $validatedData = $request->validate(
            [
                'mobile' => 'required|size:11',
            ]
        );
        $phone = $validatedData['mobile'];
        $userInfo = Users::where('phone', $phone)->first();
        if (!$userInfo) {
            throw new CustomException(ErrorCode::USER_NOT_EXISTED);
        }
        $riskRecord = RiskEvaluations::where('user_id', $userInfo['id'])->orderBy('id', 'desc')->first();
        $riskBizNo = '';
        if ($riskRecord) {
            $riskBizNo = $riskRecord['biz_no'];
        }
        $orders = [];
        $orderInfos = Orders::where('user_id', $userInfo['id'])->get()->toArray();
        foreach ($orderInfos as $orderInfo) {
            $creditId = 0;
            $record = RiskEvaluations::where('procedure_id', $orderInfo['procedure_id'])
                ->where('status', Constant::COMMON_STATUS_SUCCESS)->orderBy('id', 'desc')->first();
            if ($record) {
                $creditId = $record['biz_no'];
            }
            $repayInfo = [];
            $installments = OrderInstallments::where('order_id', $orderInfo['id'])
                ->where('status', Constant::ORDER_STATUS_PAID_OFF)->get()->toArray();
            foreach ($installments as $installment) {
                $repayInfo[] = [
                    'plan_date' => strtotime($installment['date']),
                    'num' => $installment['period'],
                    'real_date' => strtotime($installment['pay_off_time']),
                ];
            }
            $orders[] = [
                'date_unit' => 2,
                'account' => $orderInfo['amount'],
                'order_sn' => $orderInfo['biz_no'],
                'application_id' => $creditId,
                'product' => self::PRODUCT,
                'product_channel' => 'app',
                'register_channel' => $userInfo['reg_channel'],
                'created_time' => strtotime($orderInfo['created_at']),
                'fee_rate' => $orderInfo['interest_rate'] / 10000.0,
                'repay_type' => '按月还款',
                'fee_type' => '',
                'biz_status' => self::ORDER_STATUS_DICT[$orderInfo['status']] ?? 0,
                'p_use' => $orderInfo['loan_usage'],
                'deadline' => $orderInfo['periods'],
                'repay_info' => $repayInfo,
            ];
        }
        $data = [
            'application_id' => $riskBizNo,
            'old_user_id' => $userInfo['old_user_id'],
            'uid' => $userInfo['uid'],
            'order_history' => $orders,
        ];
        return $this->render($data);
    }
}