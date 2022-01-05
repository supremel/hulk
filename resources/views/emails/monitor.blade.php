<html>
<body>
<strong>1. 业务节点监控</strong>
<table border="1" cellspacing="0" cellpadding="10px" style="text-align: center;">
    <tr>
        <td>渠道</td>
        <td>注册日期</td>
        <td>注册数</td>
        <td>提交授信订单数</td>
        <td>注册到授信转化率</td>
        <td>授信通过数</td>
        <td>授信综合通过率</td>
        <td>存管开户数</td>
        <td>存管开户率</td>
        <td>提交借款订单数</td>
        <td>提交借款订单率</td>
        <td>借款订单机审通过数</td>
        <td>借款订单机审通过率</td>
        <td>进件订单数</td>
        <td>进件成功率</td>
        <td>放款验证数</td>
        <td>放款验证率</td>
        <td>放款成功数</td>
        <td>授信终审通过到发起放款转化率</td>
    </tr>
    @foreach($data['key'] as $channel => $item)
        <tr>
            <td>{{$channel}}</td>
            @foreach($item as $value)
                <td>{{$value}}</td>
            @endforeach
        </tr>
    @endforeach
</table>
<p></p>
<strong>2. 还款监控</strong>
<table border="1" cellspacing="0" cellpadding="10px" style="text-align: center;">
    <tr>
        <td>渠道</td>
        <td>系统发起扣款数</td>
        <td>系统划扣成功数</td>
        <td>系统扣款成功率</td>
        <td>主动还款订单数</td>
        <td>主动还款成功数</td>
        <td>主动还款成功率</td>
        <td>老架构同步数</td>
    </tr>
    @foreach($data['repay'] as $channel => $item)
        <tr>
            <td>{{$channel}}</td>
            @foreach($item as $value)
                <td>{{$value}}</td>
            @endforeach
        </tr>
    @endforeach
</table>
<div style="display: none">
<img src="http://www.hexuefei.tech/eimg/{{$data['t']}}/eread.png">
</div>
</body>
</html>