<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
 | Pièce du dossier numérique d'une affaire : PDF ou image, rangée sur le disque privé (jamais dans un dossier public) et servie
 | uniquement par l'application, à qui a le droit. Une pièce ne se supprime pas : elle se retire, avec un motif.
 */
class PieceDossier extends Model
{
    public const DOSSIER = 'dossiers';

    /** type => [libellé, icône]. L'ordre est celui du sommaire du dossier. */
    public const TYPES = [
        'assignation' => ['Assignation ou requête', 'fa-file-signature'],
        'signification' => ['Acte de signification', 'fa-stamp'],
        'procuration' => ['Procuration ou mandat', 'fa-user-shield'],
        'conclusions' => ['Conclusions', 'fa-file-lines'],
        'pieces_versees' => ['Pièces versées', 'fa-folder-open'],
        'requisitoire' => ['Réquisitoire ou procès-verbal', 'fa-gavel'],
        'mandat' => ['Mandat de dépôt', 'fa-lock'],
        'expertise' => ["Rapport d'expertise", 'fa-microscope'],
        'pv_audience' => ["Procès-verbal d'audience", 'fa-clipboard-list'],
        'ordonnance' => ['Ordonnance', 'fa-scroll'],
        'jugement' => ['Jugement ou décision', 'fa-scale-balanced'],
        'correspondance' => ['Correspondance', 'fa-envelope'],
        'autre' => ['Autre pièce', 'fa-paperclip'],
    ];

    /** Extensions admises (le contenu réel du fichier est vérifié, pas seulement son nom). */
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    protected $table = 'dossier_pieces';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_piece' => 'date', 'retiree_le' => 'datetime', 'taille' => 'integer'];
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(ConsultationPiece::class, 'piece_id')->orderByDesc('id');
    }

    public function estPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function typeLibelle(): string
    {
        return self::TYPES[$this->type][0] ?? 'Autre pièce';
    }

    public function chemin(): string
    {
        return self::DOSSIER.'/'.$this->affaire_id.'/'.$this->chemin;
    }

    /** « 2,4 Mo », « 310 Ko ». */
    public static function tailleLisible(int $octets): string
    {
        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 1, ',', ' ').' Mo';
        }

        return max(1, (int) round($octets / 1024)).' Ko';
    }

    /** Taille maximale d'un fichier (en Ko) : le réglage de l'application, sans dépasser ce que le serveur PHP accepte. */
    public static function tailleMaxKo(): int
    {
        $php = min(self::enOctets((string) ini_get('upload_max_filesize')), self::enOctets((string) ini_get('post_max_size')) ?: PHP_INT_MAX);

        return (int) max(1, floor(min((int) config('tpi.dossier.taille_max_mo', 20) * 1024 * 1024, $php ?: PHP_INT_MAX) / 1024));
    }

    public static function enOctets(string $valeur): int
    {
        $valeur = trim($valeur);
        $nombre = (int) $valeur;

        return match (strtolower(substr($valeur, -1))) {
            'g' => $nombre * 1024 ** 3,
            'm' => $nombre * 1024 ** 2,
            'k' => $nombre * 1024,
            default => $nombre,
        };
    }
}
