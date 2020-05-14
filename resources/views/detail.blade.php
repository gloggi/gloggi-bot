<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GloggiBot Report</title>
    <link href="{{ URL::asset('/tailwind.min.css') }}" rel="stylesheet">
    <link rel="icon" href="{{ URL::asset('/favicon.png') }}" type="image/x-icon"/>
    <link href="{{ URL::asset('/font.css') }}" rel="stylesheet">
    <style>body { font-family: Source Sans Pro } body > div { display: none }</style>
</head>
<body class="bg-gray-200 py-4">
<div class="block container mx-auto">
    <h1 class="text-xl font-semibold text-center mb-6"><a href="{{ URL::route('report') }}">GloggiBot Report</a></h1>
    <div class="max-w-md mx-auto my-4 p-6 bg-white rounded-lg shadow-xl">
        <h4 class="text-xl font-semibold text-gray-900 leading flex mb-5"><img src="{{ URL::asset('/bot.svg') }}" class="object-scale-down h-6 mr-2 opacity-50" /><span class="flex-grow opacity-75">{{ $name }}</span></h4>
        @foreach($levelChanges as $levelChange)
            <div class="text-base leading-normal flex w-full my-3">
                <span class="text-black flex-grow">{{ $levelChange->level }}</span>
                <span class="text-gray-600 flex-none">{{ $levelChange->created_at->diffForHumans() }}</span>
            </div>
        @endforeach
    </div>
</div>
</body>
</html>
