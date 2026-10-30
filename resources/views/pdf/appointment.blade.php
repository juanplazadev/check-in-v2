@extends('pdf.layout', [
    'record' => $appointment,
    'title' => __('messages.accountHistoryRecord.appointmentDocumentTitle'),
])

@section('sections')
    <div class="section">
        <h2>{{ __('messages.accountHistoryRecord.locationHeading') }}</h2>
        <dl>
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.date'), 'value' => $appointment['date']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.time'), 'value' => $appointment['timeRange']])
            @if (filled($appointment['loadType']))
                @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.loadType'), 'value' => $appointment['loadType']])
            @endif
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.locationHeading'), 'value' => $appointment['locationName']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.address'), 'value' => $appointment['locationAddress']])
            @if (filled($appointment['dock']))
                @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.dock'), 'value' => $appointment['dock']])
            @endif
        </dl>
    </div>

    <div class="section">
        <h2>{{ __('messages.accountHistoryRecord.shipmentHeading') }}</h2>
        <dl>
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.bookedOn'), 'value' => $appointment['bookedOn']])

            @if (filled($appointment['purchaseOrders']))
                <div class="row">
                    <dt>{{ __('messages.accountHistoryRecord.purchaseOrders') }}</dt>
                    <dd>
                        <span class="chips">
                            @foreach ($appointment['purchaseOrders'] as $number)
                                <span class="chip">{{ $number }}</span>
                            @endforeach
                        </span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="section">
        <h2>{{ __('messages.accountHistoryRecord.driverHeading') }}</h2>
        <dl>
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.driverName'), 'value' => $appointment['driversName']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.driverPhone'), 'value' => $appointment['driversCellphone']])
        </dl>
    </div>

    @if (filled($appointment['cancelledAt']))
        <div class="section">
            <h2>{{ __('messages.accountHistoryRecord.statusCancelled') }}</h2>
            <dl>
                @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.cancelledOn'), 'value' => $appointment['cancelledAt']])
                @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.cancellationReason'), 'value' => $appointment['cancelledReason']])
            </dl>
        </div>
    @endif
@endsection
