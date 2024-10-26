@if ($message = Session::get('success'))
    <div class="notification is-success">
        <button class="delete"></button>

        <p>{!! $message !!}</p>
    </div>
@endif

@if ($errors->any())
    <div class="notification is-danger">
        <button class="delete"></button>

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif