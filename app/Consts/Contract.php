<?php
/**
 * 常量定义
 * User: hexuefei
 * Date: 2019-06-23
 * Time: 18:56
 */

namespace App\Consts;


class Contract
{
    const STEP_GEN_DISPENSE = 1; // 合同任务分配

    const STEP_GEN_PDF_DATA = 2; // 生成PDF数据

    const STEP_GEN_PDF_FILE = 3; // 生成PDF文件

    const STEP_GEN_PDF_SIGN = 4; // 生成签章

    const TYPE_LOAN = 1; // 满标放款生成的合同

    const TYPE_AUTH = 2; // 认证完成生成的合同

    const TYPE_BANK = 3;//绑定银行卡生成的代扣合同

    const RELATION_TYPE_ORDER = 0; // 合同关联类型-订单

    const RELATION_TYPE_USER = 1; // 合同关联类型-用户

    const AGREEMENT_ENTRUST_GUARANTEE = 1001; // 水莲金条委托担保申请合同

    const AGREEMENT_BORROWER_SERVICE = 1002; // 借款人服务协议

    const AGREEMENT_FACE_BANK = 1003; // 借款人服务协议

    const AGREEMENT_DATA_PARSING = 1004; // 数据解析协议

    const AGREEMENT_PERSONAL_INFORMATION = 1005; // 个人信息使用授权书

    const  AGREEMENT_DEDUCTION_PAYMENT = 1006;   // 委托代扣还款协议

    const SIGN_CA_USER = 0; // 用户签章

    const SIGN_CA_COMPANY = 1; // 企业签章

    const TYPE_RELATION = [
        self::TYPE_LOAN => [
            self::AGREEMENT_ENTRUST_GUARANTEE,
            self::AGREEMENT_BORROWER_SERVICE,
        ],
        self::TYPE_AUTH => [
            self::AGREEMENT_DATA_PARSING,
            self::AGREEMENT_PERSONAL_INFORMATION,
        ],
        self::TYPE_BANK=>[
            self::AGREEMENT_DEDUCTION_PAYMENT,
        ]
    ];

    const AGREEMENT_RELATION = [
        self::AGREEMENT_ENTRUST_GUARANTEE => [
            'title' => '委托担保申请',
            'sign' => [self::SIGN_CA_USER],
            'view' => 'contracts.guarantee',
        ],
        self::AGREEMENT_BORROWER_SERVICE => [
            'title' => '借款人服务协议',
            'sign' => [self::SIGN_CA_USER, self::SIGN_CA_COMPANY],
            'view' => 'contracts.borrowerservice',
        ],
        self::AGREEMENT_DATA_PARSING => [
            'title' => '数据解析服务协议',
            'sign' => [self::SIGN_CA_USER],
            'view' => 'contracts.dataparsing',
        ],
        self::AGREEMENT_PERSONAL_INFORMATION => [
            'title' => '个人信息使用授权书',
            'sign' => [self::SIGN_CA_USER],
            'view' => 'contracts.personalinformation',
        ],
        self::AGREEMENT_DEDUCTION_PAYMENT => [
            'title' => '委托代扣还款协议',
            'sign' => [self::SIGN_CA_USER],
            'view' => 'contracts.deductionpayment',
        ],
    ];

    const STEP_RELATION = [
        self::STEP_GEN_DISPENSE => 'App\Services\HulkEvents\Contracts\Dispense',
        self::STEP_GEN_PDF_DATA => 'App\Services\HulkEvents\Contracts\GenPdfData',
        self::STEP_GEN_PDF_FILE => 'App\Services\HulkEvents\Contracts\GenPdfFile',
        self::STEP_GEN_PDF_SIGN => 'App\Services\HulkEvents\Contracts\GenPdfSign',
    ];

}


