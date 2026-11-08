@extends('emails.layout')

@section('titre', "Le test d'envoi a réussi")

@section('contenu')
  <p style="margin:0 0 14px;">Bonjour,</p>
  <p style="margin:0 0 18px;">Si vous lisez ce message, <b>le serveur de courriel de l'application est bien configuré</b> : les codes de connexion et les messages de création de compte arriveront dans les boîtes de réception.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#F0F9F4;border-radius:10px;">
    <tr>
      <td style="padding:14px 18px;font-size:14px;color:#374151;">
        <div style="margin-bottom:6px;"><span style="color:#6B7280;">Serveur utilisé</span><br><b style="color:#00613D;">{{ $serveur }}</b></div>
        <div style="margin-bottom:6px;"><span style="color:#6B7280;">Expéditeur</span><br><b style="color:#00613D;">{{ $expediteur }}</b></div>
        <div><span style="color:#6B7280;">Test demandé par</span><br><b style="color:#00613D;">{{ $demandeur }}</b></div>
      </td>
    </tr>
  </table>
@endsection

@section('pied')
  Ce message de test ne contient aucune information confidentielle. Vous pouvez le supprimer.
@endsection
