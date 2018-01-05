#!/usr/bin/env php
<?php
/**
 * MakeSum.php
 *
 * Script CLI de création de sommaire.
 *
 * @author    Nicolas DUPRE
 * @release   05/01/2018
 * @version   0.0.0
 * @package   Index
 *
 */

/**
 * Version 1.0.0 : 05/01/2018
 * --------------------------
 *
 *
 */

namespace MakeSummary;

use Exception;
use InvalidArgumentException;

class MakeSum
{
    /**
     * Liste des différentes options utilisée dans la classe Command.
     */
    const OPTIONS = [
        'colors' => [
            'color_err' => '196',
            'color_in' => '220',
            'color_suc' => '76',
            'color_war' => '208',
            'color_txt' => '221',
            'color_kwd' => '39'
        ],
        'separator' => ',',
        'shortopt' => "hd:",
        "longopt" => [
            "help",
            "dir:"
        ],
        "lang" => [
            "markdown" => [
                "extension" => "/md$/i",
                "insertTag" => '[](MakeSummary)',
                "openTag" => '[](BeginSummary)',
                "closeTag" => '[](EndSummary)'
            ]
        ],
        "aliases" => [
            "md" => "markdown"
        ]
    ];

    /**
     * @var string $workdir Dossier de travail
     */
    protected $workdir = null;

    /**
     * @var string $cmdName Nom de la commande
     */
    protected $cmdName = null;

    /**
     * @var array $argv
     */
    protected $argv = null;

    /**
     * @var string $defaultLang Langage par default définie pour traiter l'emplacement demandé.
     */
    protected $defaultLang = "markdown";

    /**
     * @var bool|resource $psdtout Pointeur vers la ressource de sortie standard.
     */
    protected $psdtout = STDOUT;

    /**
     * @var bool|resource $pstderr Pointeur vers la ressource de sortie des erreurs.
     */
    protected $pstderr = STDERR;

    /**
     * @var bool $noDie Flag pour ne pas jouer les evenements die.
     */
    protected $noDie = false;

    /**
     * Constructor function.
     *
     * @param string $workdir Path to working directory.
     * @param array  $argv    Array of command line arguments.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($workdir, array $argv, $cmdName)
    {
        $workdir = trim($workdir);
        if (empty($workdir)) {
            throw new InvalidArgumentException("workdir parameter in constructor can't be empty.");
        }
        if (!is_dir($workdir)) {
            throw new InvalidArgumentException("workdir `{$workdir}` doesn't exist.");
        }
        $this->workdir = $workdir;
        $this->argv = $argv;
        $this->cmdName = $cmdName;

    }

    /**
     * Exécution du script.
     *
     * @return bool
     */
    public function run ()
    {
        $options = $this->argv;
        $showHelp = true;

        $directory = @($options["d"]) ?: (@$options["dir"]) ?: $this->workdir;

        $fullPath = (preg_match("#^\/#", $directory)) ? $directory : $this->workdir . '/' . $directory;

        // Afficher l'aide si demandée et s'arrêter là.
        if (
            array_key_exists("h", $options)
            || array_key_exists("help", $options)
        ) {
            $this->help();
            return true;
        }

        //

        return true;
    }

    /**
     * Affiche le manuel d'aide.
     *
     * @param int $level
     *
     * @return void
     */
    protected function help($level = 0)
    {
        $separator = self::OPTIONS['separator'];
        $name = $this->cmdName;

        $man = <<<HELP
        
Usage : $name [OPTIONS]



-d, --dir   Spécifie l'emplacement de travail.
HELP;
        fwrite($this->psdtout, $man . PHP_EOL);
        if ($level) die($level);
    }

    /**
     * Met en évidence les valeurs utilisateur dans les messages
     *
     * @param  string $message Message à analyser
     *
     * @return string $message Message traité
     */
    protected function highlight($message)
    {
        $color_in = self::OPTIONS['colors']['color_in'];

        // A tous ceux qui n'ont pas de couleur spécifiée, alors saisir la couleur par défaut
        $message = preg_replace("/(?<!>)(%[a-zA-Z0-9])/", "$color_in>$1", $message);

        // Remplacer par le code de colorisation Shell
        $message = preg_replace("#([0-9]+)>(%[a-zA-Z0-9])#", "\e[38;5;$1m$2\e[0m", $message);

        return $message;
    }

    /**
     * Emet des messages dans le flux STDERR de niveau WARNING ou ERROR
     *
     * @param string $message Message à afficher dans le STDERR
     * @param array  $args    Elements à introduire dans le message
     * @param int    $level   Niveau d'alerte : 0 = warning, 1 = error
     *
     * @return void
     */
    protected function stderr($message, array $args = [], $level = 1)
    {
        // Connexion aux variables globales
        $color_err = self::OPTIONS['colors']['color_err'];
        $color_war = self::OPTIONS['colors']['color_war'];

        // Traitement en fonction du niveau d'erreur
        $level_str = ($level) ? "ERROR" : "WARNING";
        $color = ($level) ? $color_err : $color_war;

        // Mise en evidence des saisie utilisateur
        $message = $this->highlight($message);
        $message = "[ \e[38;5;{$color}m$level_str\e[0m ] :: $message" . PHP_EOL;

        fwrite($this->pstderr, vsprintf($message, $args));
        if ($level && !$this->noDie) die($level);
    }

    /**
     * Emet des messages dans le flux classique STDOUT
     *
     * @param string $message Message à afficher dans le STDOUT
     * @param array  $arg     Elements à introduire dans le message
     */
    protected function stdout($message, $args = [])
    {
        $options = self::OPTIONS;

        if (!isset($options["silent"])) {
            $message = $this->highlight($message);
            $message = "[ INFO ] :: $message".PHP_EOL;
            fwrite($this->psdtout, vsprintf($message, $args));
        }
    }

    /**
     * Définie la ressource de sortie standard.
     *
     * @param bool|resource $stdout Pointeur vers une ressource ayant un accès en écriture.
     */
    public function setStdout($stdout = STDOUT)
    {
        $this->psdtout = $stdout;
    }

    /**
     * Définie la ressource de sortie des erreurs.
     *
     * @param bool|resource $stderr Pointeur vers une ressource ayant un accès en écriture.
     */
    public function setStderr($stderr = STDERR)
    {
        $this->pstderr = $stderr;
    }

    /**
     * Définie le comportement des fonctions die.
     *
     * @param bool $nodie
     */
    public function setNoDie($nodie = false)
    {
        $this->noDie = $nodie;
    }

}



/**
 * Instanciation à la volée et exécution.
 */
$options = getopt(
    MakeSum::OPTIONS['shortopt'],
    MakeSum::OPTIONS['longopt']
);

$commandName = basename($_SERVER['SCRIPT_NAME']);

(new MakeSum($_SERVER["PWD"], $options, $commandName))->run();
