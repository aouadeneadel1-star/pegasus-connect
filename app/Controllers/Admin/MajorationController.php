<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Règles de majoration et jours fériés. Une règle n'est jamais modifiée : une nouvelle règle
 * prend effet à sa date, ce qui conserve le calcul des mois passés.
 */
final class MajorationController extends Controller
{
    private const CODES = ['nuit' => 'Nuit', 'dimanche' => 'Dimanche', 'ferie' => 'Jour férié', 'hs25' => 'Heures supplémentaires, premier taux', 'hs50' => 'Heures supplémentaires, second taux'];

    public function index(Request $request): Response
    {
        $annee = (int) $request->query('annee', Clock::now()->setTimezone(Clock::local())->format('Y'));
        $annee = $annee >= 2020 && $annee <= 2100 ? $annee : (int) Clock::now()->format('Y');

        return $this->vue('admin/majorations', [
            'regles' => $this->factory->majorations()->regles(),
            'codes' => self::CODES,
            'annee' => $annee,
            'feries' => $this->factory->majorations()->feries($annee),
        ]);
    }

    public function ajouterRegle(Request $request): Response
    {
        $validation = Validator::make($request->all(), [
            'code' => 'required|in:' . implode(',', array_keys(self::CODES)),
            'libelle' => 'required|max:80',
            'taux' => 'required|decimal|between:0,300',
            'plage_debut' => 'time',
            'plage_fin' => 'time',
            'seuil_heures' => 'decimal|between:0,48',
            'date_effet' => 'required|date',
        ], ['code' => 'Le type', 'libelle' => 'Le libellé', 'taux' => 'Le taux', 'date_effet' => 'La date d\'effet', 'seuil_heures' => 'Le seuil']);
        if ($validation->fails()) {
            return $this->redirigerAvecErreur('/admin/majorations', self::premiereErreur($validation));
        }

        $donnees = $validation->validated();
        $id = $this->factory->majorations()->ajouterRegle([
            'code' => (string) $donnees['code'],
            'libelle' => (string) $donnees['libelle'],
            'taux' => str_replace(',', '.', (string) $donnees['taux']),
            'plage_debut' => $donnees['plage_debut'],
            'plage_fin' => $donnees['plage_fin'],
            'seuil_heures' => $donnees['seuil_heures'] === null ? null : str_replace(',', '.', $donnees['seuil_heures']),
            'cumulable' => $request->boolean('cumulable') ? 1 : 0,
            'date_effet' => (string) $donnees['date_effet'],
        ]);
        $this->factory->audit()->journaliser('majoration.ajouter', 'regle_majoration', $id, ['code' => $donnees['code'], 'taux' => $donnees['taux']]);

        return $this->rediriger('/admin/majorations', 'Règle ajoutée. Elle s\'applique aux services commencés à partir du ' . date_courte((string) $donnees['date_effet']) . '.');
    }

    public function desactiverRegle(Request $request): Response
    {
        $id = $request->intParam('id');
        $this->factory->majorations()->desactiverRegle($id);
        $this->factory->audit()->journaliser('majoration.desactiver', 'regle_majoration', $id);

        return $this->rediriger('/admin/majorations', 'Règle désactivée.');
    }

    public function ajouterFerie(Request $request): Response
    {
        $validation = Validator::make($request->all(), ['date' => 'required|date', 'libelle' => 'required|max:60'], ['date' => 'La date', 'libelle' => 'Le libellé']);
        if ($validation->fails()) {
            return $this->redirigerAvecErreur('/admin/majorations', self::premiereErreur($validation));
        }

        $donnees = $validation->validated();
        $this->factory->majorations()->ajouterFerie((string) $donnees['date'], (string) $donnees['libelle']);
        $this->factory->audit()->journaliser('ferie.ajouter', null, null, ['date' => $donnees['date']]);

        return $this->rediriger('/admin/majorations?annee=' . substr((string) $donnees['date'], 0, 4), 'Jour férié enregistré.');
    }

    public function supprimerFerie(Request $request): Response
    {
        $date = self::date($request->input('date'), '');
        if ($date !== '') {
            $this->factory->majorations()->supprimerFerie($date);
            $this->factory->audit()->journaliser('ferie.supprimer', null, null, ['date' => $date]);
        }

        return $this->rediriger('/admin/majorations?annee=' . substr($date, 0, 4), 'Jour férié supprimé.');
    }
}
