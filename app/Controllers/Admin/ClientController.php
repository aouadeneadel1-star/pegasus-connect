<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Perimetre;

final class ClientController extends Controller
{
    private const LIBELLES = [
        'raison_sociale' => 'La raison sociale',
        'siret' => 'Le SIRET',
        'contact_nom' => 'Le nom du contact',
        'contact_email' => 'L\'e-mail du contact',
        'contact_tel' => 'Le téléphone du contact',
    ];

    public function index(Request $request): Response
    {
        return $this->vue('admin/clients/index', ['clients' => $this->factory->clients()->lister()]);
    }

    public function nouveau(Request $request): Response
    {
        return $this->vue('admin/clients/formulaire', ['client' => null, 'options' => ['noms_agents' => false, 'validite_carte_pro' => false], 'sites' => [], 'comptes' => []]);
    }

    public function creer(Request $request): Response
    {
        $validation = $this->valider($request);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/clients/formulaire', $validation->errors(), ['client' => null, 'options' => $this->options($request), 'sites' => [], 'comptes' => []]);
        }

        $id = $this->factory->clients()->creer($this->donnees($request, $validation));
        $this->factory->audit()->journaliser('client.creer', 'client', $id);

        return $this->rediriger('/admin/clients/' . $id, 'Client créé. Ajoutez ses sites puis ses comptes d\'accès au portail.');
    }

    public function editer(Request $request): Response
    {
        $client = $this->factory->clients()->trouver($request->intParam('id')) ?? throw HttpException::notFound();

        return $this->vue('admin/clients/formulaire', [
            'client' => $client,
            'options' => $this->factory->clients()->options((int) $client['id']),
            'sites' => $this->factory->sites()->lister(Perimetre::tout(), ['client' => (int) $client['id'], 'actif' => 'tous']),
            'comptes' => $this->factory->clients()->comptes((int) $client['id']),
        ]);
    }

    public function enregistrer(Request $request): Response
    {
        $client = $this->factory->clients()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $validation = $this->valider($request);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/clients/formulaire', $validation->errors(), [
                'client' => $client,
                'options' => $this->options($request),
                'sites' => $this->factory->sites()->lister(Perimetre::tout(), ['client' => (int) $client['id'], 'actif' => 'tous']),
                'comptes' => $this->factory->clients()->comptes((int) $client['id']),
            ]);
        }

        $this->factory->clients()->modifier((int) $client['id'], $this->donnees($request, $validation));
        $this->factory->audit()->journaliser('client.modifier', 'client', (int) $client['id']);

        return $this->rediriger('/admin/clients/' . $client['id'], 'Client enregistré.');
    }

    private function valider(Request $request): Validator
    {
        return Validator::make($request->all(), [
            'raison_sociale' => 'required|max:150',
            'siret' => 'digits:14',
            'contact_nom' => 'max:120',
            'contact_email' => 'email|max:190',
            'contact_tel' => 'regex:/^[+0-9 .()-]{8,20}$/',
        ], self::LIBELLES);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function donnees(Request $request, Validator $validation): array
    {
        $donnees = $validation->validated();

        return [
            'raison_sociale' => (string) $donnees['raison_sociale'],
            'siret' => $donnees['siret'],
            'contact_nom' => $donnees['contact_nom'],
            'contact_email' => $donnees['contact_email'],
            'contact_tel' => $donnees['contact_tel'],
            'options_portail' => json_encode($this->options($request), JSON_THROW_ON_ERROR),
            'actif' => $request->boolean('actif') ? 1 : 0,
        ];
    }

    /**
     * Ce que le client voit de nos agents : par défaut, ni leur nom ni la validité de leur carte.
     *
     * @return array{noms_agents: bool, validite_carte_pro: bool}
     */
    private function options(Request $request): array
    {
        return [
            'noms_agents' => $request->boolean('noms_agents'),
            'validite_carte_pro' => $request->boolean('validite_carte_pro'),
        ];
    }
}
