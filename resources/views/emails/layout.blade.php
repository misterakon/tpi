{{-- Gabarit commun des courriels : bandeau orange, blanc, vert ; logo du tribunal ; bouton vert.
     Mise en page en tableaux et styles « en ligne » : c'est ce que les messageries comprennent le mieux. --}}
@php
    $logo = public_path('assets/images/logo/logo_tpi_embleme.png');
    // Dans un vrai envoi, le logo est joint au message (« cid »). En aperçu ou en test, on retombe sur l'adresse du site.
    $logoSrc = isset($message) && is_file($logo) ? $message->embed($logo) : asset('assets/images/logo/logo_tpi_embleme.png');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('titre')</title>
</head>
<body style="margin:0;padding:0;background:#EEF2EF;font-family:'Trebuchet MS','Lucida Grande',Tahoma,Arial,Helvetica,sans-serif;color:#1F2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF2EF;padding:28px 12px;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.10);">

        {{-- Bandeau tricolore --}}
        <tr>
          <td height="8" style="font-size:0;line-height:0;background:#E8710A;background-image:linear-gradient(90deg,#E8710A 0%,#E8710A 33%,#FFFFFF 33%,#FFFFFF 66%,#00794C 66%,#00794C 100%);">&nbsp;</td>
        </tr>

        {{-- En-tête : logo et nom --}}
        <tr>
          <td align="center" style="padding:26px 24px 8px;">
            <img src="{{ $logoSrc }}" width="92" alt="{{ config('app.name') }}" style="display:block;margin:0 auto 10px;border:0;">
            <div style="font-size:20px;font-weight:800;letter-spacing:.5px;">
              <span style="color:#E8710A;">{{ config('tpi.sigle') }}</span> <span style="color:#00794C;">{{ config('tpi.ville') }}</span>
            </div>
            <div style="font-size:12px;color:#6B7280;margin-top:3px;">{{ config('tpi.juridiction') }}</div>
          </td>
        </tr>

        {{-- Contenu --}}
        <tr>
          <td style="padding:14px 36px 8px;font-size:15px;line-height:1.6;">
            <h1 style="margin:14px 0 14px;font-size:22px;line-height:1.3;color:#0F172A;">@yield('titre')</h1>
            @yield('contenu')
          </td>
        </tr>

        {{-- Pied --}}
        <tr>
          <td style="padding:18px 36px 28px;">
            <div style="border-top:1px solid #E5E7EB;padding-top:16px;font-size:12px;line-height:1.6;color:#6B7280;">
              @yield('pied')
              <div style="margin-top:8px;">Ce message est envoyé automatiquement : merci de ne pas y répondre.</div>
            </div>
          </td>
        </tr>

        <tr>
          <td height="8" style="font-size:0;line-height:0;background:#00794C;background-image:linear-gradient(90deg,#00794C 0%,#00794C 33%,#FFFFFF 33%,#FFFFFF 66%,#E8710A 66%,#E8710A 100%);">&nbsp;</td>
        </tr>
      </table>
      <div style="font-size:11px;color:#9CA3AF;margin-top:14px;">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
    </td></tr>
  </table>
</body>
</html>
