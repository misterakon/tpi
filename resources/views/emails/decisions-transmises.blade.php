@extends('emails.layout')

@section('titre', count($fichiers) > 1 ? count($fichiers).' décisions transmises au greffe' : 'Une décision transmise au greffe')

@section('contenu')
  @php $n = count($fichiers); @endphp
  <p style="margin:0 0 14px;">Bonjour,</p>
  <p style="margin:0 0 18px;line-height:1.55;"><b>{{ $expediteur }}</b> vient de transmettre au greffe {{ $n > 1 ? 'les '.$n.' décisions ci-dessous' : 'la décision ci-dessous' }}, à la date du <b>{{ $dateTransmission }}</b>. Cette date vaut <b>date de délivrance</b> au greffe. {{ $n > 1 ? 'Chaque décision est jointe à ce message en fichier Word (.docx)' : 'La décision est jointe à ce message en fichier Word (.docx)' }}, avec la mise en page de la feuille d'audience.</p>

  @foreach ($fichiers as $f)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;background:#F0F9F4;border-left:4px solid #00794C;border-radius:8px;">
      <tr>
        <td style="padding:14px 18px;font-size:14px;line-height:1.55;color:#1F2937;">
          <div style="font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#00613D;">Décision n° {{ $f['numero'] }}</div>
          <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:8px;font-size:14px;color:#374151;">
            <tr><td style="padding:2px 14px 2px 0;color:#6B7280;">Affaire</td><td style="padding:2px 0;"><b>{{ $f['affaire'] }}</b></td></tr>
            @if ($f['chambre'])<tr><td style="padding:2px 14px 2px 0;color:#6B7280;">Chambre</td><td style="padding:2px 0;">{{ $f['chambre'] }}</td></tr>@endif
            @if ($f['type'])<tr><td style="padding:2px 14px 2px 0;color:#6B7280;">Type</td><td style="padding:2px 0;">{{ $f['type'] }}</td></tr>@endif
            @if ($f['signee'])<tr><td style="padding:2px 14px 2px 0;color:#6B7280;">Signée le</td><td style="padding:2px 0;">{{ $f['signee'] }}</td></tr>@endif
            <tr><td style="padding:2px 14px 2px 0;color:#6B7280;">Fichier joint</td><td style="padding:2px 0;">{{ $f['nom'] }}</td></tr>
          </table>
        </td>
      </tr>
    </table>
  @endforeach

  <p style="margin:16px 0 6px;line-height:1.55;"><b>À faire au greffe :</b> délivrer la décision aux parties (expédition, grosse ou copie) et la classer.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0 6px;">
    <tr><td style="background:#00794C;border-radius:8px;">
      <a href="{{ $lien }}" style="display:inline-block;padding:12px 22px;font-weight:700;font-size:15px;color:#ffffff;text-decoration:none;">Ouvrir {{ $n > 1 ? 'le registre des décisions' : 'la décision' }}</a>
    </td></tr>
  </table>
@endsection

@section('pied')
  Ce message est envoyé automatiquement par l'application du tribunal. Les décisions jointes sont confidentielles : ne les faites pas suivre. Il ne cite aucun nom de partie.
@endsection
