<x-mail::message>

## ¡Hola{{ isset($user_name) && $user_name ? ', ' . explode(' ', trim($user_name))[0] : '' }}!

{!! nl2br(e($body)) !!}

<x-mail::button :url="config('app.frontend_url', 'https://app.cyberteconline.com')">
Ver en la plataforma
</x-mail::button>

**Saludos,**
S!NTyC

</x-mail::message>
