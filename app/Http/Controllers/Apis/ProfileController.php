<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-12
 * Time: 12:24
 */

namespace App\Http\Controllers\Apis;

use App\Common\OssClient;
use App\Common\Utils;
use App\Consts\Banks;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Profile;
use App\Consts\Scheme;
use App\Consts\Text;
use App\Exceptions\CustomException;
use App\Helpers\AuthStatus\AuthStatus;
use App\Helpers\Locker;
use App\Helpers\Validator;
use App\Http\Controllers\Controller;
use App\Models\AddrInfo;
use App\Models\AuthInfo;
use App\Models\BankCard;
use App\Models\BaseInfo;
use App\Models\IdCard;
use App\Models\Relationship;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function identityInfo(Request $request)
    {
        $user = $request->user;
//        $rec = IdCard::where('user_id', $user['id'])->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
        $front = '';
        $back = '';
//        if ($rec) {
//            $front = $rec['front_id'];
//            $back = $rec['back_id'];
//        }
        $face = '';
//        $rec = AuthInfo::where('user_id', $user['id'])
//            ->where('type', Constant::DATA_TYPE_FACE)->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
//        if ($rec) {
//            $face = $rec['extra'];
//        }
        $data = [
            'id_card_front' => $front, //OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_FRONT, $front),
            'id_card_back' => $back, //OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_BACK, $back),
            'face_recognition' => $face,
            'back_alert' => Text::AUTH_BACK_ALERT,
            'back_link' => Scheme::AUTH_BACK_LINK,
            'id_card_front_oss' => sprintf(OssClient::PATH_DICT[Constant::FILE_TYPE_ID_CARD_FRONT]['path_format'],
                $user['uid'], Constant::FILE_TYPE_ID_CARD_FRONT),
            'id_card_back_oss' => sprintf(OssClient::PATH_DICT[Constant::FILE_TYPE_ID_CARD_BACK]['path_format'],
                $user['uid'], Constant::FILE_TYPE_ID_CARD_BACK),
            'face_oss' => sprintf(OssClient::PATH_DICT[Constant::FILE_TYPE_FACE]['path_format'],
                $user['uid'], Constant::FILE_TYPE_FACE),
        ];
        return $this->render($data);
    }

    public function basic(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'education' => 'required|in:01,02,03,04',
                'industry' => 'required|in:01,02,03,04,05,06,07,08,09,10,11,12,13,14',
                'month_income' => 'required|in:01,02,03,04,05',
                'company_name' => 'required',
                'province' => 'required|regex:/^[0-9]+$/',
                'city' => 'required|regex:/^[0-9]+$/',
                'county' => 'required|regex:/^[0-9]+$/',
                'addr' => 'required',
                'email' => 'email',
            ]
        );
        $validatedData['company_name'] = strip_tags($validatedData['company_name']);
        $validatedData['addr'] = strip_tags($validatedData['addr']);
        $validatedData['user_id'] = $user['id'];
        $validatedData['status'] = Constant::AUTH_STATUS_SUCCESS;

        BaseInfo::create($validatedData);

        return $this->render([]);
    }

    public function realName(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'name' => 'required',
                'identity' => 'required|size:18|regex:/^[0-9]{17}[0-9xX]+$/',
                'front_id' => 'required',
                'back_id' => 'required',
                'face_id' => 'required',
            ]
        );
        if (!Validator::validateChineseName(trim($validatedData['name']))) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '请输入正确的姓名');
        }
        if (!Validator::validateIdentity(trim($validatedData['identity']))) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '请输入正确的身份证号码');
        }
        $birthYear = substr($validatedData['identity'], 6, 4);
        $age = intval(date('Y')) - intval($birthYear);
        if ($age < Constant::USER_AGE_MIN || $age > Constant::USER_AGE_MAX) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '您的年龄超过限制了');
        }

        $validatedData['user_id'] = $user['id'];
        $validatedData['status'] = Constant::AUTH_STATUS_SUCCESS;


        $lockerKey = 'real_name';
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 60, '')) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '请稍后重试');
        }
        try {
            if (Users::where('identity', $validatedData['identity'])
                ->where('id', '!=', $user['id'])->exists()) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '身份证号码已占用');
            }
            DB::transaction(function () use ($user, $validatedData) {
                AuthInfo::create([
                        'biz_no' => Utils::genBizNo(),
                        'user_id' => $user['id'],
                        'type' => Constant::DATA_TYPE_FACE,
                        'extra' => $validatedData['face_id'],
                        'status' => Constant::AUTH_STATUS_SUCCESS,
                    ]
                );
                Users::where('id', $user['id'])->update(
                    ['name' => $validatedData['name'], 'identity' => $validatedData['identity'],]);
                unset($validatedData['name']);
                unset($validatedData['identity']);
                IdCard::create($validatedData);
            });
        } catch (\Exception $e) {
            $locker->restoreLock($lockerKey, '');
            throw $e;
        }

        $locker->restoreLock($lockerKey, '');
        return $this->render([]);
    }

    public function basicInfo(Request $request)
    {
        $data = [
            'educations' => Profile::EDUCATIONS,
            'industries' => Profile::INDUSTRIES,
            'month_incomes' => Profile::MONTH_INCOMES,
            'provinces' => AddrInfo::where('province', 0)->where('city', 0)->get()->toArray(),
            "back_alert" => Text::AUTH_BACK_ALERT,
            "back_link" => Scheme::AUTH_BACK_LINK,
        ];
        return $this->render($data);
    }

    public function relationshipInfo(Request $request)
    {
        $user = $request->user;
        $recs = Relationship::where('user_id', $user['id'])
            ->where('status', Constant::AUTH_STATUS_SUCCESS)
            ->orderBy('type')
            ->get()->toArray();
        $relationships = [];
        if ($recs) {
            foreach ($recs as $rec) {
                $relationships[] = [
                    'name' => $rec['name'],
                    'type' => $rec['type'],
                    'phone' => Utils::maskPhone($rec['phone']),
                    'relation' => $rec['relation'],
                ];
            }
        }

        $allRelations = [];
        foreach (Profile::RELATIONSHIPS as $k => $v) {
            foreach ($v as $val) {
                $val['t'] = $k;
                $allRelations[] = $val;
            }
        }

        $data = [
            'relationship_num' => Profile::RELATIONSHIP_NUM,
            'relationships' => $relationships,
            'family_relations' => Profile::RELATIONSHIPS[Profile::RELATIONSHIP_TYPE_FAMILY],
            'emergency_relations' => Profile::RELATIONSHIPS[Profile::RELATIONSHIP_TYPE_EMERGENCY],
            'all_relations' => $allRelations,
            'back_alert' => Text::AUTH_BACK_ALERT,
            'back_link' => Scheme::AUTH_BACK_LINK,
        ];
        return $this->render($data);
    }

    public function relationship(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'relationships' => 'required|json',
            ]
        );
        $relationships = json_decode($validatedData['relationships']);
        if (!is_array($relationships) || count($relationships) != Profile::RELATIONSHIP_NUM) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, "紧急联系人个数错误");
        }

        $lastType = -1;
        $lastPhone = '';

        $relationArr = [];
        $phoneArr = [];
        $nameArr = [];

        foreach ($relationships as $relationship) {
            $validator = \Illuminate\Support\Facades\Validator::make((array)$relationship, [
                'name' => 'required',
                'type' => 'required|in:0,1',
                'phone' => 'required|regex:/^[0-9\*\+]{6,}$/',
                'relation' => 'required',
            ]);
            if ($validator->fails()) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, "紧急联系人格式错误");
            }
            $item = $validator->validated();

//            $phone = $item['phone'];
//            $type = $item['type'];
//            $relation = $item['relation'];
//            // 异常数据，两条相同type的数据
//            if ($type == $lastType) {
//                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, "relationships格式错误[3]");
//            }
//            if ($phone == $lastPhone) {
//                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, "直属亲属和紧急联系人不能为同一号码");
//            }
//            $lastType = $type;
//            $lastPhone = $phone;

            if(in_array($item['relation'], $relationArr)) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '紧急联系人的关系不能重复');
            }
            if(in_array($item['phone'], $phoneArr) || in_array($item['name'], $nameArr)) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '填写的联系人姓名、电话有重复信息，请核对更新');
            }
            // 手机号没变，则忽略
            if (strpos($item['phone'], '*') !== false) {
                continue;
            }
            if (empty(Profile::findValueByKey(Profile::RELATIONSHIPS[$item['type']], $item['relation']))) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '关系值错误');
            }
            // 将现有数据置为失效,再插入
            Relationship::where('user_id', $user['id'])->where('relation', $item['relation'])
                ->update(['status' => Constant::AUTH_STATUS_EXPIRED]);
            $item['status'] = Constant::AUTH_STATUS_SUCCESS;
            $item['user_id'] = $user['id'];
            Relationship::create($item);

            $relationArr[] = $item['relation'];
            $phoneArr[] = $item['phone'];
            $nameArr[] = $item['name'];
        }

        return $this->render([]);
    }

    public function bankInfo(Request $request)
    {
        $user = $request->user;
        $bankName = '';
        $cardNo = '';
        $bankCard = BankCard::where('user_id', $user['id'])->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
            ->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
        if ($bankCard) {
            $bankName = Banks::CODE_NAME_MAPPINGS[$bankCard['bank_code']];
            $cardNo = $bankCard['card_no'];
        }
        $banks = Banks::LIST;
        foreach ($banks as &$bank) {
            $bank['icon'] = empty($bank['icon']) ? $bank['icon'] : OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, $bank['icon']);
        }
        $contracts = [
            Utils::genNavigationItem(
                '',
                sprintf(
                    Scheme::APP_WEBVIEW_FORMAT,
                    urlencode(env('H5_BASE_URL').Scheme::H5_DEDUCTION_AGREEMENT),
                    urlencode('《委托代扣还款协议》')
                ),
                '《委托代扣还款协议》',
                '',
                '',
                '',
                ''),
        ];
        $data = [
            "contracts" =>$contracts,
            'bank_name' => $bankName,
            'bank_card_no' => Utils::maskCardNo($cardNo),
            'name' => Utils::maskChineseName($user['name']),
            'back_alert' => Text::AUTH_BACK_ALERT,
            'back_link' => Scheme::AUTH_BACK_LINK,
            'banks' => $banks,
        ];
        return $this->render($data);
    }

    public function tpAuthList(Request $request)
    {
        $user = $request->user;
        $items = [];
        foreach (Profile::AUTH_LIST[Profile::AUTH_TYPE_REQUIRED_THIRD] as $dataType => $item) {
            $title = $item['title'];
            $icon = $item['icon'];
            $icon = OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, $icon);
            $link = $item['link'];
            $authStatus = new AuthStatus();
            $tip = '去认证';
            if ($authStatus->getAuthItemStatus($user['id'], $dataType)) {
                $link = '';
                $tip = '已完成';
            }
            $statisticId = '';
            if ($dataType == Constant::DATA_TYPE_PHONE) {
                $statisticId = 'C07006';
            }
//            else if ($dataType == Constant::DATA_TYPE_TAOBAO) {
//                $statisticId = 'C07005';
//            }
            $items[] = Utils::genNavigationItem($icon, $link, $title, $tip,
                '', '', $statisticId);
        }
        $contracts = [];
        $contracts[] = Utils::genNavigationItem('', 'https://www.baidu.com',
            '《三方认证授权协议》');
        $data = [
            'items' => $items,
            'back_alert' => Text::AUTH_BACK_ALERT,
            'back_link' => Scheme::AUTH_BACK_LINK,
            'contracts' => $contracts,
        ];
        return $this->render($data);
    }
}