<?php

namespace App\Support;

class OnboardingDefinition
{
    public const VERSION = 2;

    public static function steps(string $product): array
    {
        return match ($product) {
            'corretor' => [
                ['id' => 'profile', 'title' => 'Completar perfil profissional', 'href' => '/app/configuracoes'],
                ['id' => 'search', 'title' => 'Conhecer a pesquisa de imóveis', 'href' => '/app/corretor/pesquisar'],
                ['id' => 'save', 'title' => 'Salvar a primeira oportunidade', 'href' => '/app/corretor/favoritos'],
                ['id' => 'capture', 'title' => 'Entender a autorização de captação', 'href' => '/app/corretor/captacao'],
                ['id' => 'wallet', 'title' => 'Adicionar o primeiro imóvel autorizado', 'href' => '/app/corretor/carteira'],
                ['id' => 'site', 'title' => 'Configurar a página de vendas', 'href' => '/app/corretor/meu-site'],
                ['id' => 'crm', 'title' => 'Conhecer o CRM de contatos', 'href' => '/app/corretor/leads'],
            ],
            'imobiliaria' => [
                ['id' => 'org', 'title' => 'Configurar dados da imobiliária', 'href' => '/app/imobiliaria/site'],
                ['id' => 'branch', 'title' => 'Criar a primeira filial, se aplicável', 'href' => '/app/imobiliaria/filiais'],
                ['id' => 'team', 'title' => 'Convidar a equipe, se desejado', 'href' => '/app/imobiliaria/equipes'],
                ['id' => 'listing', 'title' => 'Cadastrar o primeiro imóvel', 'href' => '/app/imobiliaria/imoveis'],
                ['id' => 'client', 'title' => 'Cadastrar um cliente', 'href' => '/app/imobiliaria/clientes'],
                ['id' => 'ticket', 'title' => 'Criar um atendimento', 'href' => '/app/imobiliaria/atendimentos'],
                ['id' => 'crm', 'title' => 'Conhecer o CRM', 'href' => '/app/imobiliaria/crm'],
                ['id' => 'site', 'title' => 'Configurar o site institucional', 'href' => '/app/imobiliaria/site'],
            ],
            'house_flipper' => [
                ['id' => 'region', 'title' => 'Definir região de interesse', 'href' => '/app/house-flipper/mapa'],
                ['id' => 'search', 'title' => 'Conhecer a pesquisa', 'href' => '/app/house-flipper/oportunidades'],
                ['id' => 'save', 'title' => 'Salvar uma oportunidade', 'href' => '/app/house-flipper/oportunidades'],
                ['id' => 'project', 'title' => 'Criar o primeiro projeto', 'href' => '/app/house-flipper/projetos'],
                ['id' => 'simulator', 'title' => 'Utilizar o simulador financeiro', 'href' => '/app/house-flipper/simulador'],
                ['id' => 'budget', 'title' => 'Configurar orçamento', 'href' => '/app/house-flipper/despesas'],
                ['id' => 'viz3d', 'title' => 'Explorar as ferramentas 3D disponíveis', 'href' => '/app/house-flipper/projetos-3d'],
            ],
            default => [],
        };
    }
}
