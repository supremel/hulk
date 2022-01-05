@component('mail::message')
# Date

- {{$date}}

# Message

- {{$msg}}

# Params

@foreach ($params as $k=>$v)
- {{$k}}={{$v}}
@endforeach

# Headers

@foreach ($headers as $k=>$v)
@if (is_array($v))
- {{$k}}={{str_replace('"', '',json_encode($v))}}
@else
- {{$k}}={{$v}}
@endif
@endforeach

# Trace

{{$trace}}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
