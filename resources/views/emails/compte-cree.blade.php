@extends('emails.layout')

@section('titre', 'Bienvenue, votre compte est créé')

@section('contenu')
  <p style="margin:0 0 14px;">Bonjour <b>{{ $nom }}</b>,</p>
  <p style="margin:0 0 18px;">Un compte vient de vous être créé sur l'application de gestion du <b>{{ config('tpi.juridiction') }}</b>.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background:#F0F9F4;border-radius:10px;">
    <tr>
      <td style="padding:14px 18px;font-size:14px;color:#374151;">
        <div style="margin-bottom:6px;"><span style="color:#6B7280;">Identifiant (e-mail)</span><br><b style="color:#00613D;">{{ $email }}</b></div>
        <div><span style="color:#6B7280;">Profil</span><br><b style="color:#00613D;">{{ $profil }}</b></div>
      </td>
    </tr>
  </table>

  @if ($lien)
    <p style="margin:0 0 18px;">Pour commencer, choisissez votre mot de passe :</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
      <tr><td align="center" style="background:#00794C;border-radius:10px;">
        <a href="{{ $lien }}" style="display:inline-block;padding:14px 30px;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;">Choisir mon mot de passe</a>
      </td></tr>
    </table>
    <p style="margin:0 0 14px;font-size:13px;color:#6B7280;">Ce lien est valable {{ $validite }} minutes et ne peut servir qu'une fois. S'il a expiré, demandez à l'administrateur de vous en envoyer un nouveau.</p>
    <p style="margin:0 0 6px;font-size:12px;color:#9CA3AF;">Le bouton ne fonctionne pas ? Copiez cette adresse dans votre navigateur :<br><span style="word-break:break-all;color:#6B7280;">{{ $lien }}</span></p>
  @else
    <p style="margin:0 0 14px;">Votre mot de passe vous a été <b>communiqué par l'administrateur</b>. Nous vous conseillons de le changer dès votre première connexion (menu « Mon compte »).</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 14px;">
      <tr><td align="center" style="background:#00794C;border-radius:10px;">
        <a href="{{ route('login') }}" style="display:inline-block;padding:14px 30px;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;">Me connecter</a>
      </td></tr>
    </table>
  @endif

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 6px;">
    <tr><td style="background:#FFF4E8;border-left:4px solid #E8710A;border-radius:6px;padding:12px 14px;font-size:13px;color:#7C2D0A;">
      À chaque connexion, un <b>code à 6 chiffres</b> vous sera envoyé par e-mail. Ne le communiquez à personne.
    </td></tr>
  </table>
@endsection

@section('pied')
  Vous n'attendiez pas ce message ? Vous pouvez l'ignorer, ou le signaler à l'administrateur.
@endsection
