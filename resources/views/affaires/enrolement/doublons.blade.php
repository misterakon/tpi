{{-- Alerte de doublon probable avec la case de confirmation (variable : $doublons) --}}
@if ($doublons)
  <div class="aff-doublons">
    <b>Affaire déjà enregistrée avec les mêmes parties :</b>
    <ul>
      @foreach ($doublons as $d)
        <li><a href="{{ route('affaires.liste.fiche', $d['id']) }}" target="_blank" rel="noopener">{{ $d['numero'] }}</a> — {{ \Illuminate\Support\Str::limit($d['objet'], 90) }}</li>
      @endforeach
    </ul>
    <label class="aff-confirmer"><input type="checkbox" name="confirmer_doublon" value="1" form="form-enrolement"> Il s'agit bien d'une autre affaire : enregistrer quand même</label>
  </div>
@endif
