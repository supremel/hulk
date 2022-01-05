<?php
/**
 * 短信内容相关常量
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-26
 * Time: 15:51
 */

namespace App\Consts;


class SmsContent
{
    // 授信失败
    const RISK_EVALUATION_REFUSE = '很抱歉，您的综合情况暂不符合申请条件，感谢您的支持。';

    // 授信通过
    const RISK_EVALUATION_RECEPTION_FORMAT = '您申请的%d元借款已获批，请尽快登录app开通存管账户后提现，最快可当日下款到账。如有疑问请致电客服4000-611-622。';

    // 授信通过T+1/T+7 (开户提醒)
    const OPEN_ACCOUNT_REMIND_FORMAT = '您申请的%d元借款已获批，请尽快登录app开通存管账户后提现，最快可当日下款到账。如有疑问请致电客服4000-611-622。';

    // 开户成功
    const OPEN_ACCOUNT_SUCCESS_FORMAT = '存管账户开通成功，请'
    . Constant::BORROW_ORDER_CONFIRM_TIME . '日内确认借款。最快当日下款到账。';

    // 开户失败
    const OPEN_ACCOUNT_FAIL = '存管账户开通失败。如有疑问请关注“水莲金条”微信公众号在线咨询或致电客服4000-611-622。';

    // 借款审核通过
    const BORROW_ORDER_RECEPTION_FORMAT = '恭喜您，您申请%d元借款审核通过，请在'
    . Constant::WITHDRAW_FINISH_TIME . '分钟内完成放款验证。如有疑问请致电客服4000-611-622。';

    // 借款审核拒绝
    const BORROW_ORDER_REFUSE = '很抱歉，您的借款申请审批拒绝，感谢您的支持。';

    // 放款验证提醒（当日，进件成功）
    const WITHDRAW_REMIND_CURRENT_FORMAT = '您申请的借款%d元待放款验证，完成验证，款项将在自动打款到您尾号%s的银行卡中。'
    . '超时未验证系统将取消借款。如有疑问请致电客服4000-611-622。';

    // 放款验证提醒（T+1～3，进件成功）
    const WITHDRAW_REMIND_FORMAT = '您申请的借款%d元待放款验证，请在'
    . Constant::WITHDRAW_REMIND_TIME . '小时内完成，验证通过后，款项将在自动打款到您尾号%s的银行卡中。超时未验证系统将取消借款。如有疑问请致电客服4000-611-622。';

    // 放款到账
    const WITHDRAW_SUCCESS_FORMAT = '您的借款%d元，在尾号%s的银行卡中已到账，首期应还款%.2f元，还款日%s月%s日。';

    // 还款提醒（还款日前3天，9:00, 16:00）
    const REPAY_REMIND_FORMAT = '您本期账单待还%.2f元，还款日%s月%s日。请在到期前主动还款，保证卡内余额充足且开通银联在线支付。若已还款请忽略此短信。如有疑问请致电客服4000-611-622。';

    // 还款提醒（当天, 9:00, 16:00）
    const REPAY_REMIND_CURRENT_FORMAT = '您本期账单待还%.2f元，今日已到期。您可登录app主动还款，请保证卡内余额充足且开通银联在线支付。若已还款请忽略此短信。如有疑问请致电客服4000-611-622。';

    // 还款成功
    const REPAY_SUCCESS_SINGLE_PERIOD_FORMAT = '您于%s月%s日成功还款%.2f元，第%d期账单已结清，共%d期。';
    const REPAY_SUCCESS_PAID_OFF_FORMAT = '您于%s月%s日成功还款%.2f元，本次借款已结清。再次申请可获得极速放款。';

    // 还款失败
    const REPAY_FAIL_SINGLE_PERIOD_FORMAT = '还款失败。本期应还金额%.2f元，请确保还款卡内余额充足，或登录水莲金条APP使用其它还款方式还款。';
    const REPAY_FAIL_PAID_OFF_FORMAT = '还款失败。借款应还总额%.2f元，请确保还款卡内余额充足，或登录水莲金条APP使用其它还款方式还款。';
}