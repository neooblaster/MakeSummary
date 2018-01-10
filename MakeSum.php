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
 * @TODO : Activer / Désactiver la numérotation.
 * @TODO : Selectionner un modèle de numérotation.
 * @TODO : Définir la plage de niveau de réalisation du sommayre (def, 0 to unlimitd).
 * @TODO : Définir la plage de niveau de numérotation automatique.
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
            "debug",
            "dir:",
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
     * @var array $langAliases Liste d'alias pointant vers la configuration exacte pour le language à traiter.
     */
    protected $langAliases = [
        "markdown" => "markdown",
        "md" => "markdown"
    ];

    /**
     * @var array $langRegister Registre des configurations fonctionnel par language de développement.
     */
    protected $langRegister = [
        "markdown" => [
            "extension" => "/\.md$/i",
            "insertTag" => '[](MakeSummary)',
            "openTag" => '[](BeginSummary)',
            "closeTag" => '[](EndSummary)',
            // @TODO : MakeSummary\linkable
            "linkable" => true,
            // @TODO : MakeSummary\createAnchor
            "createAnchor" => false,
            // @TODO : MakeSummary\style
            "style" => "none",
            "substitution" => [
                "chars" => [
                    "\s" => "-",
                    "\." => ""
                ],
                "functions" => [
                    "urlencode" => [],
                    "strtolower" => []
                ],
                // $t Emplacement de la tabulation.
                // $x Insertion du match numéro x.
                // $s Insertion de la substitution.
                "final" => '$t* [$2](#$s)'
            ],
            "tabulated" => true,
            "eol" => true,
            "tabsize" => 4,
            "taboffset" => 0,
            "title" => [
                "pattern" => "/^\s*(#+)\s*(.*)$/m",
                "levelMatch" => 1,
                "stringMatch" => 2,
                // string, (pattern, number)
                "levelType" => "string",
                "levelIndicator" => "#"
            ],
            "startLevel" => 2,
            "endLevel" => 9
        ]
    ];

    /**
     * @var array $langsToProcess Liste des langues à traiter.
     */
    protected $langsToProcess = [];

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

        $this->addLangToProcess($this->defaultLang);

    }

    /**
     * Exécution du script.
     *
     * @return bool
     */
    public function __run ()
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

        // Traitement
        $this->parse($fullPath);

        return true;
    }

    /**
     * Enregistre un alias de nom de langue vers un nom reel.
     *
     * @param string $aliasName Nom d'alias au format chaine de caractère alphanumérique.
     * @param string $realName  Nom de langue réel permetant l'utilisation de la configuration associée.
     *
     * @return true
     */
    public function addAlias ($aliasName, $realName)
    {
        // Peut-on trouver une configuration associée ?
        if (!array_key_exists($realName, $this->langRegister)) $this->stderr(
            'The configuration %s is not registred. Please use registerLang.', [$realName]
        );

        $this->langAliases[$aliasName] = $realName;

        return true;
    }

    /**
     * Ajoute une langue à traiter.
     *
     * @param string $langName      Nom d'usage de la langue.
     * @param string $configName    Nom réel dont il faudra utiliser la configuration.
     *
     * @return bool
     */
    public function addLangToProcess ($langName, $configName = 'markdown')
    {
        // Si la langue n'est pas dans la liste des langages à traiter.
        if (!in_array($langName, $this->langsToProcess)) {
            $this->langsToProcess[] = $langName;

            $this->addAlias($langName, $configName);
        }

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

    protected function parse ($path)
    {
        // S'il l'emplacement demandé n'existe pas, on stop le process.
        if (!file_exists($path)) $this->stderr("The following path %s does not exist", [$path]);

        // S'il s'agit d'un dossier, le parcourir.
        if (is_dir($path)) {
            $dir = opendir($path);

            while ($file = readdir($dir)) {
                // Ignorer les référnces de navigation.
                if (preg_match("/^\.{1,2}$/", $file)) continue;

                $this->parse($path . '/' . $file);
            }

            closedir($dir);
        }
        // S'il s'agit d'un fichier, l'analyser.
        else {
            /**
             * Phase d'initialisation :: Données transverses.
             *
             * @var string $configName      Nom réel correspondant à la configuration à utiliser.
             * @var array  $options         Options fournies dans la ligne de commande.
             * @var bool   $summaryUpdate   Indique s'il s'agit d'une mise à jour de sommaire.
             * @var bool   $continue        Indique si l'on continue le processus de sommairisation.
             * @var bool   $debug           Affiche des messages détaillés pour le debugguage.
             */
            $configName = null;
            $options = $this->argv;
            $summaryUpdate = false;
            $continue = false;
            $debug = array_key_exists('debug', $options);



            /**
             * Phase de contrôle.
             *
             * Récupération du fichier.
             *
             * @var string $filename Nom du fichier au format name.ext
             */
            $filename = basename($path);


            /**
             * Est-ce un fichier à traiter.
             *
             * @var bool $return Indicateur demandant l'envois final false. Fin du traitement.
             */
            $return = true;

            foreach ($this->langsToProcess as $langIdx => $langName) {
                $realName = $this->langAliases[$langName];
                $extension = $this->langRegister[$realName]['extension'];

                if (preg_match($extension, $filename)) {
                    $configName = $realName;
                    $return = false;
                    break;
                }
            }

            if ($return) return false;


            /**
             * Phase de traitement du texte.
             *
             * @var string  $text            Text à traiter.
             * @var array   $config          Ensemble des paramètre de configuration du language.
             * @var string  $insertTag       Balise d'insertion du sommaire.
             * @var string  $openTag         Balise ouvrante du sommaire.
             * @var string  $closeTag        Balise fermante du sommaire.
             * @var integer $startLevel      Niveau à partir duquel on commence le sommaire.
             * @var integer $endLevel        Niveau à partir duquel on arrête le sommaire.
             * @var boolean $tabulated       Indique s'il faut généré des tabulations en accord avec le niveau.
             * @var integer $tabsize         Lorsque tabulé, indique la taille en espace de la tabulation.
             * @var integer $taboffset       Permet d'ajouter un décallage avant ou arriere (-) pour la tabulation.
             * @var array   $subChar         Liste des substitution de caractères.
             * @var array   $subFunc         Liste des functons à appliqué sur le titre substitué.
             * @var boolean $eol             Indique qu'il faut inséré un retour chariot.
             * @var array   $titleCfg        Ensemble des paramètre de configuration pour l'analyse des titres.
             * @var string  $titlePattern    Modèle d'identification des titres.
             * @var string  $levelMatch      Index de capture dans lequel l'indicateur de niveau de titre est stocké.
             * @var string  $levelType       Type de l'élément permettant l'identification du niveau du titre.
             * @var string  $levelIndicator  Modèle d'identification du niveau de titre.
             * @var string  $stringMatch     Index de capture dans lequel le titre est stocké.
             */
            $text = file_get_contents($path);

            $config = $this->langRegister[$configName];

            $insertTag = $config['insertTag'];
            $openTag = $config['openTag'];
            $closeTag = $config['closeTag'];

            $startLevel = $config['startLevel'];
            $endLevel = $config['endLevel'];

            $tabulated = $config['tabulated'];
            $tabsize = $config['tabsize'];
            $taboffset = $config['taboffset'];

            $subChar = $config['substitution']['chars'];
            $subFunc = $config['substitution']['functions'];

            $eol = $config['eol'];

            $titleCfg = $config['title'];
            $titlePattern = $titleCfg['pattern'];
            $levelMatch = $titleCfg['levelMatch'];
            $levelType = strtolower($titleCfg['levelType']);
            $levelIndicator = $titleCfg['levelIndicator'];
            $stringMatch = $titleCfg['stringMatch'];



            /**
             * Est-ce que les indicateurs de construction automatique du sommaire sont présent ?
             */
            // Indicateur de création de sommaire
            if (preg_match(preg_quote("/$insertTag/"), $text)) {
                $continue = true;
            }
            // Indicateurs de présence de sommaire => Mise à jour
            if (
                !$continue
                && preg_match(preg_quote("/$openTag/"), $text)
                && preg_match(preg_quote("/$closeTag/"), $text)
            ) {
                $continue = true;
                $summaryUpdate = true;
            }

            if (!$continue) return false;


            /**
             * Récupération de tous les titres.
             */
            preg_match_all($titlePattern, $text, $titles);


            /**
             * Inversion du regroupement des matches
             */
            $this->preg_match_reverse_grouping($titles);


            /**
             * Calcul du niveau du titre
             */
            foreach ($titles as $index => &$title) {
                $levelStr = $title[$levelMatch];
                $level = 0;

                if ($levelType === 'string') {
                    $level = substr_count($levelStr, $levelIndicator);
                }

                $title['titleLevel'] = $level;
            }
            unset($title);

            if ($debug) {
                $this->stdout(
                    ">>> Please find below matched titles %s",
                    [print_r($titles, true)],
                    "DEBUG"
                );
                $this->stdout("<<< End of Title Calculation.", [], "DEBUG");
            }


            /**
             * Création du sommaire
             */
            $numerization = [];
            $summary = "";

            foreach ($titles as $index => $title) {
                /**
                 * @var string  $entry         Entrée de sortie, manipulé au fur est à mesure des processus de
                 * construction.
                 *
                 * @var integer $level         Niveau du titre en valeur numérique.
                 * @var string  $stringTitle   Reprise du titre tel qu'il est présent dans le document.
                 * @var string  $substitution  Titre de substitution utilisé dans l'ancrage.
                 */
                $entry = $config['substitution']['final'];
                $level = $title["titleLevel"];
                $stringTitle =  $title[$stringMatch];
                $substitution = $stringTitle;

                // Si le niveau n'est pas admis, on l'ignore
                if (!($level >= $startLevel && $level <= $endLevel)) continue;

                // Gestion de la tabulation si demandée.
                if ($tabulated) {
                    $naturalOffset = 1 - $startLevel;
                    $multiplier = ($level - 1 + $taboffset + $naturalOffset) * $tabsize;

                    $entry = str_replace('$t', str_repeat(" ",$multiplier), $entry);
                }

                // Insertion du texte d'origine.
                $entry = str_replace(
                    '$' . $stringMatch,
                    $stringTitle,
                    $entry
                );

                // Procéder aux opération de substitution.
                // Caractères (char)
                foreach ($subChar as $src => $sub) {
                    $substitution = preg_replace("/$src/", $sub, $substitution);
                }
                // Application de function (functions)
                foreach ($subFunc as $fn => $arg) {
                    $substitution = call_user_func_array($fn, array_merge([$substitution], $arg));
                }
                // Intégration de la substitution
                $entry = str_replace('$s', $substitution, $entry);

                // Si demandé, insertion d'un EndOfLine.
                if ($eol) {
                    $entry .= PHP_EOL;
                }

                $summary .= $entry;
            }

            if ($debug) {
                $this->stdout(
                    ">>> Please find below the generated summary : \n\n%s",
                    [$summary],
                    "DEBUG"
                );
                $this->stdout("<<< End of Generated Summary.", [], "DEBUG");
            }


            /**
             * Intégration du sommaire dans le document
             */
            if ($summaryUpdate) {
                $summaryBlockPattern = "/(" . preg_quote($openTag) .
                    ")(.*)(" . preg_quote($closeTag) .
                    ")/s";

                $text = preg_replace($summaryBlockPattern,
                        "$1\n$summary$3",
                        $text
                );
            } else {
                $summary = $openTag . PHP_EOL . $summary . PHP_EOL . $closeTag . PHP_EOL;
                $text = preg_replace(preg_quote("/$insertTag/"), $summary, $text);
            }


            /**
             * Mise à jour du fichier
             */
            if ($debug) {
                $this->stdout("1%s", ["In debug mode, no changes are applied to files."], "DEBUG");
            }
            else {
                file_put_contents($path, $text);
            }
        }

        return true;
    }

    /**
     * Regroupe les matches au sein du même tableau au lieu du regroupement par défaut.
     *
     * @param array $array Référence à inverser.
     *
     * @return bool
     */
    protected function preg_match_reverse_grouping (Array &$array)
    {
        if (!count($array)) return false;

        if (!is_array($array[0]) || !count($array[0])) return false;

        $matches = count($array);
        $reversed = [];

        foreach ($array[0] as $index => $match) {
            $instance = [];
            $instance[0] = $match;

            for ($i = 1; $i < $matches; $i++) {
                $instance[$i] = $array[$i][$index];
            }

            $reversed[$index] = $instance;
        }

        $array = $reversed;

        return true;
    }

    public function setLangsToProcess ()
    {

    }

    public function registerLang ()
    {

    }

    public function removeAlias ()
    {

    }

    public function removeLangToProcess ()
    {

    }

    public function unregisterLang ()
    {

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
    protected function stdout($message, $args = [], $title = 'INFO')
    {
        $options = self::OPTIONS;

        if (!isset($options["silent"])) {
            $message = $this->highlight($message);
            $message = "[ $title ] :: $message".PHP_EOL;
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

(new MakeSum($_SERVER["PWD"], $options, $commandName))->__run();
