<?php
/**
 * 身份证ocr
 * User: hexuefei
 * Date: 2019-07-19
 * Time: 18:16
 */

namespace App\Console\Commands\Auth;


use App\Common\OssClient;
use App\Common\ShangtangClient;
use App\Common\Utils;
use App\Common\YoutuClient;
use App\Consts\Constant;
use App\Helpers\Locker;
use App\Models\IdCard;
use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdCardOcrCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'auth:id_card_ocr';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '认证-身份证OCR识别';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 商汤ocr
     * @param $frontUrl
     * @param $backUrl
     * @return array
     */
    private function _ocrByShangtang($frontUrl, $backUrl)
    {
        $data = [];
        if ($frontUrl == '' || $backUrl == '') {
            return $data;
        }
        $frontRet = ShangtangClient::ocr(base64_encode(file_get_contents($frontUrl)));
        $backRet = ShangtangClient::ocr(base64_encode(file_get_contents($backUrl)));

        if ($frontRet && $backRet && 'back' == $backRet['side'] && 'front' == $frontRet['side']) {
            $frontInfo = $frontRet['info'];
            $backInfo = $backRet['info'];
            if (strlen($frontInfo['number']) != 18) {
                return $data;
            }
            $validDate = explode('-', $backInfo['timelimit']);
            if (count($validDate) == 2) {
                if (0 == preg_match('/[0-9]{8}/', $validDate[0])) {
                    return $data;
                }
                if ('长期' == $validDate[1]) {
                    $validDate = [
                        substr($validDate[0], 0, 4) . '.' . substr($validDate[0], 4, 2) . '.' . substr($validDate[0], -2),
                        '长期',
                    ];
                } else {
                    if (0 == preg_match('/[0-9]{8}/', $validDate[1])) {
                        return $data;
                    }
                    $validDate = [
                        substr($validDate[0], 0, 4) . '.' . substr($validDate[0], 4, 2) . '.' . substr($validDate[0], -2),
                        substr($validDate[1], 0, 4) . '.' . substr($validDate[1], 4, 2) . '.' . substr($validDate[1], -2),
                    ];
                }
            } else {
                $validDate = ['-', '-'];
            }
            $data = [
                'name' => $frontInfo['name'],
                'identity' => $frontInfo['number'],
                'age' => Utils::getAgeByIdentity($frontInfo['number']),
                'gender' => ($frontInfo['gender'] == '女') ? Constant::GENDER_WOMEN : Constant::GENDER_MEN,
                'ethnicity' => $frontInfo['nation'],
                'birthday' => $frontInfo['year'] . '/' . $frontInfo['month'] . '/' . $frontInfo['day'],
                'addr' => $frontInfo['address'],
                'start_time' => $validDate[0],
                'end_time' => $validDate[1],
                'issued_by' => $backInfo['authority'],
                'status' => Constant::AUTH_STATUS_SUCCESS,
                'extra' => '',
            ];
        }
        return $data;
    }

    /**
     * 优图ocr
     * @param $frontUrl
     * @param $backUrl
     * @return array
     */
    private function _ocrByYoutu($frontUrl, $backUrl)
    {
        $data = [];
        $backRet = YoutuClient::ocrIdCard($backUrl, true);
        $frontRet = YoutuClient::ocrIdCard($frontUrl, false);
        if ($backRet && $frontRet) {
            $validDate = explode('-', $backRet['valid_date']);
            $data = [
                'name' => $frontRet['name'],
                'identity' => $frontRet['id'],
                'age' => Utils::getAgeByIdentity($frontRet['id']),
                'gender' => ($frontRet['sex'] == '女') ? Constant::GENDER_WOMEN : Constant::GENDER_MEN,
                'ethnicity' => $frontRet['nation'],
                'birthday' => $frontRet['birth'],
                'addr' => $frontRet['address'],
                'start_time' => $validDate[0],
                'end_time' => $validDate[1] ?? '',
                'issued_by' => $backRet['authority'],
                'status' => Constant::AUTH_STATUS_SUCCESS,
                'extra' => '',
            ];
        }
        return $data;
    }

    /**
     * 每条记录尝试ocr处理3次
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        $total = 0;
        $max = 20;
        $lastId = 99999999;
        while (true) {
            try {
                $idCard = IdCard::where('identity', '=', '')
                    ->where('id', '<', $lastId)
                    ->where('status', Constant::AUTH_STATUS_SUCCESS)
                    ->orderBy('id', 'desc')
                    ->first();
                if (!$idCard) {
                    break;
                }
                $lastId = $idCard['id'];
                $locker = new Locker();
                if (!$locker->lock('id_card_ocr_' . $idCard['id'], 30 * 60)) {
                    continue;
                }
                if (false !== strpos($idCard['back_id'], 'http')) {
                    $backUrl = $idCard['back_id'];
                    $frontUrl = $idCard['front_id'];
                } else {
                    $backUrl = OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_BACK, $idCard['back_id']);
                    $frontUrl = OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_FRONT, $idCard['front_id']);
                }

                Log::info('module=' . $this->signature . "\tmsg=ongoing\tid=" . $idCard['id'] . "\tback_url=$backUrl\tfront_url=$frontUrl");

                $tryTimes = 0;
                do {
                    $tryTimes += 1;
                    $data = $this->_ocrByShangtang($frontUrl, $backUrl);
                    if ($data) {
                        IdCard::where('id', $idCard['id'])->update($data);
                        break;
                    } else {
                        $data = $this->_ocrByYoutu($frontUrl, $backUrl);
                        if ($data) {
                            IdCard::where('id', $idCard['id'])->update($data);
                            break;
                        } else if ($tryTimes >= 3) {
                            DB::transaction(function () use ($idCard) {
                                Users::where('id', $idCard['user_id'])->update([
                                    'identity' => '',
                                ]);
                                IdCard::where('id', $idCard['id'])->update(['status' => Constant::AUTH_STATUS_FAILED]);
                            });
                            break;
                        }
                    }
                } while (true);

            } catch (\Exception $exception) {
                Log::warning('module=' . $this->signature . "\tmsg=error\terror=" . $exception->getMessage());
            }
            $total += 1;
            if ($total >= $max) {
                break;
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}
