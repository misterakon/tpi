<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertes du circuit des dossiers : deux passages par jour (heure d'Abidjan). Une alerte ne part jamais deux fois ; les jours manqués sont rattrapés.
Schedule::command('alertes:evaluer')->dailyAt('04:30')->timezone('Africa/Abidjan')->withoutOverlapping();
Schedule::command('alertes:evaluer')->dailyAt('12:30')->timezone('Africa/Abidjan')->withoutOverlapping();
