@extends('webaseo::boilerplate')

@section('title') WebaSEO - Site SEO Report Builder @endsection

@section('content')
    <div class="container py-5">
        @if(!$error)
            <div class="row">
                <div class="col">
                    <div class="row border mb-3 p-3">
                        <div class="row mb-3">
                                Summary
                        </div>
                        <div class="border-top py-3">
                            <div class="row">
                                <div class="col">
                                    <div class="w-50">
                                        <div id="semi_circle_gauge" class="apex-charts" dir="ltr"></div>
                                    </div>
                                </div>
                                <div class="col py-5">
                                    <?php echo " <strong>{$website}</strong> scored " . round($score); ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    
            <div class="row">
                <div class="col">
                    @foreach($categories as $categoryKey => $categoryValue)
                        <div class="row border mb-3 p-3 pb-0">
                            <div class="row mb-3">
                                <?php echo strtoupper($categoryKey) == "SEO" ? strtoupper($categoryKey) : ucfirst($categoryKey);  ?>
                            </div>
                            @foreach($categories[$categoryKey] as $subCategory)
                                <div class="border-top py-3">
                                    <div class="row">
                                        <div class="col-1 d-flex flex-column justify-content-center">
                                            <div class="">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" viewBox="0 0 512 512">
                                                    <!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2023 Fonticons, Inc.-->
                                                    <path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z" <?php echo $result[$subCategory]["passed"] == true ? 'fill="green"' : 'fill="red"'; ?>/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="col-11">
                                            <div class="row">
                                                    <div class="col-12 mb-2 mb-sm-0 col-sm-3 d-flex flex-column justify-content-center">
                                                            <?php echo str_replace("_", " ", ucfirst($subCategory)); ?>
                                                    </div>
                                                    <div class="col-12 mb-2 mb-sm-0 col-sm-3 d-flex flex-column justify-content-center">
                                                        <div class="alert <?php echo $result[$subCategory]["passed"] == true ? "alert-success" : "alert-danger" ?> mb-0 py-1" role="alert">
                                                           <?php echo $result[$subCategory]["passed"] == true ? "Passed" : "Failed" ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 mb-2 mb-sm-0 col-sm-6 d-flex flex-column justify-content-center">
                                                        <div class="">
                                                            @if($result[$subCategory]["passed"] == false)
                                                                <div class="alert alert-danger mb-0 py-1" role="alert">
                                                                    @php(print(config("webaseo.report_failed_errors." . $subCategory)))
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                            </div>
                                            </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col">
                <div class="row border mb-3 p-3">
                    <div class="row mb-3">
                        Result
                    </div>
                    <div class="border-top py-3">
                        <div class="row">
                            <div class="col">
                                @if(isset($requestErrorMessage))
                                    @php(print($requestErrorMessage))
                                @else
                                    There was an error processing your request. Are you running WebaSEO on localhost?
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
@section('script')
    <script>
        let success = "#198754";
        let primary = "#0d6efd";
        let warning = "#ffc107";
        let danger = "#dc3545";
        let score = <?php echo round($score); ?>;
        let color = ( score > 74 ? success : ( score > 49 ? primary : (score > 24 ? warning : danger) ) );
        $(document).ready(function (){
            var options = {
                series: [score],
                colors: [color],
                chart: {
                    type: 'radialBar',
                    offsetY: -20,
                    sparkline: {
                        enabled: true
                    }
                },
                plotOptions: {
                    radialBar: {
                        startAngle: -90,
                        endAngle: 90,
                        track: {
                            background: "#e7e7e7",
                            strokeWidth: '97%',
                            margin: 5, // margin is in pixels
                            dropShadow: {
                                enabled: true,
                                top: 2,
                                left: 0,
                                color: '#999',
                                opacity: 1,
                                blur: 2
                            }
                        },
                        dataLabels: {
                            name: {
                                show: false
                            },
                            value: {
                                offsetY: -2,
                                fontSize: '22px'
                            }
                        }
                    }
                },
                grid: {
                    padding: {
                        top: -10
                    }
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'light',
                        shadeIntensity: 0.4,
                        inverseColors: false,
                        opacityFrom: 1,
                        opacityTo: 1,
                        stops: [0, 50, 53, 91]
                    },
                },
                labels: ['Average Results'],
            };

            var chart = new ApexCharts(document.querySelector("#semi_circle_gauge"), options);
            chart.render();
        });
    </script>
@endsection