@extends('pdf.layout', [
    'record' => $checkIn,
    'title' => __('messages.accountHistoryRecord.checkInDocumentTitle'),
])

@section('sections')
    <div class="section">
        <h2>{{ __('messages.accountHistoryRecord.locationHeading') }}</h2>
        <dl>
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.date'), 'value' => $checkIn['date']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.time'), 'value' => $checkIn['time']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.locationHeading'), 'value' => $checkIn['locationName']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.address'), 'value' => $checkIn['locationAddress']])
            @if (filled($checkIn['dock']))
                @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.dock'), 'value' => $checkIn['dock']])
            @endif
        </dl>
    </div>

    <div class="section">
        <h2>{{ __('messages.accountHistoryRecord.shipmentHeading') }}</h2>
        <dl>
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.customer'), 'value' => $checkIn['customer']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.destination'), 'value' => $checkIn['destination']])

            @if (filled($checkIn['purchaseOrders']))
                <div class="row">
                    <dt>{{ __('messages.accountHistoryRecord.purchaseOrders') }}</dt>
                    <dd>
                        <span class="chips">
                            @foreach ($checkIn['purchaseOrders'] as $number)
                                <span class="chip">{{ $number }}</span>
                            @endforeach
                        </span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="section">
        <h2>{{ __('messages.accountHistoryRecord.equipmentHeading') }}</h2>
        <dl>
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.truckName'), 'value' => $checkIn['truckName']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.truckPlate'), 'value' => $checkIn['truckPlate'], 'mono' => true])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.truckColor'), 'value' => $checkIn['truckColor']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.trailerPlate'), 'value' => $checkIn['trailerPlate'], 'mono' => true])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.trailerChute'), 'value' => $checkIn['trailerChute']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.emptyWeight'), 'value' => $checkIn['emptyWeightLbs']])
        </dl>
    </div>

    <div class="section">
        <h2>{{ __('messages.accountHistoryRecord.driverHeading') }}</h2>
        <dl>
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.driverName'), 'value' => $checkIn['driversName']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.driverPhone'), 'value' => $checkIn['driversCellphone']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.driverEmail'), 'value' => $checkIn['driversEmail']])
            {{-- Masked, never the full number: the column is encrypted at rest
                 and this document is downloadable and forwardable. --}}
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.license'), 'value' => $checkIn['licenseMasked'], 'mono' => true])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.licenseState'), 'value' => $checkIn['licenseState']])
            @include('pdf.partials.row', ['label' => __('messages.accountHistoryRecord.licenseExpiration'), 'value' => $checkIn['licenseExpiration']])
        </dl>
    </div>

    @if (filled($checkIn['loadingInstructions']))
        <p class="note">
            <strong>{{ __('messages.accountHistoryRecord.loadingInstructions') }}</strong>
            {{ $checkIn['loadingInstructions'] }}
        </p>
    @endif
@endsection
