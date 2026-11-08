{{-- Messages (erreurs de validation, succès, information) affichés avec SweetAlert.
     @js encode proprement les textes : une apostrophe dans un message ne casse plus le script. --}}
@php
  $messagesErreurs = collect($errors->all())->map(fn ($erreur) => e($erreur))->implode('<br>');
  $nomApplication = config('app.name');
@endphp
<script type="text/javascript">

	@if ($messagesErreurs !== '')
		Swal.fire({ icon: 'error', title: @js($nomApplication), html: @js($messagesErreurs) });
	@endif

	@foreach (['error' => 'error', 'info' => 'info', 'success' => 'success', 'status' => 'success'] as $cle => $icone)
		@if (session($cle))
			Swal.fire({ icon: @js($icone), title: @js($nomApplication), text: @js(session($cle)) });
		@endif
	@endforeach

</script>
