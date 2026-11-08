@extends('emails.layout')

@section('titre', 'Choisir un nouveau mot de passe')

@section('contenu')
  <p style="margin:0 0 14px;">Bonjour,</p>
  <p style="margin:0 0 18px;">Une demande a été faite pour choisir un nouveau mot de passe pour votre compte. Cliquez sur le bouton ci-dessous :</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
    <tr><td align="center" style="background:#00794C;border-radius:10px;">
      <a href="{{ $lien }}" style="display:inline-block;padding:14px 30px;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;">Choisir mon mot de passe</a>
    </td></tr>
  </table>

  <p style="margin:0 0 14px;font-size:13px;color:#6B7280;">Ce lien est valable <b>{{ $minutes }} minutes</b> et ne peut servir qu'une fois.</p>
  <p style="margin:0 0 6px;font-size:12px;color:#9CA3AF;">Le bouton ne fonctionne pas ? Copiez cette adresse dans votre navigateur :<br><span style="word-break:break-all;color:#6B7280;">{{ $lien }}</span></p>
@endsection

@section('pied')
  Vous n'êtes pas à l'origine de cette demande ? Ignorez ce message : votre mot de passe actuel reste valable.
@endsection
