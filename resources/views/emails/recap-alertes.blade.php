@extends('emails.layout')

@section('titre', count($alertes).' alerte'.(count($alertes) > 1 ? 's' : '').' à traiter')

@section('contenu')
  @php
      $couleurs = ['info' => ['#E8F1FB', '#1F4E85'], 'attention' => ['#FFF4E8', '#8A3A00'], 'critique' => ['#FDECEA', '#8E1B10']];
      $noms = ['info' => 'Information', 'attention' => 'Attention', 'critique' => 'Critique'];
  @endphp
  <p style="margin:0 0 14px;">Bonjour <b>{{ $nom }}</b>,</p>
  <p style="margin:0 0 16px;">Voici les alertes qui vous concernent aujourd'hui. Chacune indique l'action à mener.</p>

  @foreach ($alertes as $a)
    @php [$fond, $texte] = $couleurs[$a['niveau']] ?? $couleurs['attention']; @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 10px;">
      <tr><td style="background:{{ $fond }};border-left:4px solid {{ $texte }};border-radius:6px;padding:12px 14px;font-size:14px;line-height:1.5;color:#1F2937;">
        <div style="font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:{{ $texte }};">{{ $noms[$a['niveau']] ?? 'Alerte' }} · {{ $a['regle'] }}</div>
        <div style="font-weight:700;margin:3px 0;">{{ $a['titre'] }}</div>
        <div>À faire : {{ $a['action'] }}</div>
        <div style="color:#4B5563;font-size:13px;margin-top:2px;">Échéance : {{ $a['echeance'] }}</div>
      </td></tr>
    </table>
  @endforeach

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 6px;">
    <tr><td style="background:#00794C;border-radius:8px;">
      <a href="{{ $lien }}" style="display:inline-block;padding:12px 22px;font-weight:700;font-size:15px;color:#ffffff;text-decoration:none;">Ouvrir mes notifications</a>
    </td></tr>
  </table>
@endsection

@section('pied')
  Ce message est un récapitulatif automatique des alertes du circuit des dossiers. Il ne contient aucun nom de partie.
@endsection
