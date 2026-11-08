@extends('emails.layout')

@section('titre', 'Votre code de connexion')

@section('contenu')
  <p style="margin:0 0 14px;">Bonjour <b>{{ $nom }}</b>,</p>
  <p style="margin:0 0 18px;">Voici le code à saisir pour terminer votre connexion :</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
    <tr><td align="center" style="background:#F0F9F4;border:2px dashed #E8710A;border-radius:12px;padding:20px 10px;">
      <span style="font-family:'Courier New',monospace;font-size:38px;font-weight:800;letter-spacing:12px;color:#00613D;">{{ $code }}</span>
    </td></tr>
  </table>

  <p style="margin:0 0 14px;">Ce code est valable <b>{{ $validite }} minute(s)</b> et ne peut servir qu'<b>une seule fois</b>.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 10px;">
    <tr><td style="background:#FFF4E8;border-left:4px solid #E8710A;border-radius:6px;padding:12px 14px;font-size:14px;color:#7C2D0A;">
      <b>Ne communiquez jamais ce code, à personne</b> : ni à un collègue, ni à un agent du tribunal ou du service informatique.
    </td></tr>
  </table>
@endsection

@section('pied')
  Vous n'êtes pas à l'origine de cette connexion ? Ignorez ce message et informez l'administrateur sans attendre : quelqu'un connaît peut-être votre mot de passe.
@endsection
