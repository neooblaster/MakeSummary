# MakeSummary

Explications sur le bloc de configuration de traitement d'une langue.

Configuration pour le ``markdown`` :

```php
// Configuration pour le "Markdown".
"markdown" => [
    // Modèle RegExp définissant les extensions de fichier Markdown.
    "extension" => "/\.md$/i",
    
    // Balise servant à indiquer où sera inséré le sommaire pour la création.
    "insertTag" => '[](MakeSummary)',
    
    // Balise servant à indiquer le début du sommaire pour la mise à jour.
    "openTag" => '[](BeginSummary)',
    
    // Balise servant à indiquer la fin du sommaire pour la mise à jour.
    "closeTag" => '[](EndSummary)',
    
    // Indique si les éléments du sommaire sont cliquable.
    "linkable" => true,
    
    // Indique si le système créer lui-même le système d'ancrage.
    "createAnchor" => false,
    
    // Règles de substitution pour la réalisation de l'ancrage
    "substitution" => [
        "\s" => "-",
        "\." => "",
        
        // Fonctions à appliquer sur l'ancrage
        "functions" => [
            // Nom de fonction => // Arguments à passer
            "urlencode" => []
        ]
    ],
    
    // Taille d'une tabulation en unitée d'espace
    "tabsize" => 4,
    
    // Modèle identifiant un titre Markdown.****
    "titlePattern" => "/^\s*#(.*)$/Um",
    
    // Modèle définissant le niveau du titre identifié.
    "levelPattern" => "",
]
```
