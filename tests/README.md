# Exécution des tests

Il y à deux processus à contrôler :
* La création d'un nouveau sommaire.
* La mise à jour d'un sommaire existant.

Pour la réalisation des tests, j'ai créé 3 fichiers pour chaque processus :

* Un fichier ``in``
* Un fichier ``expected``
* Un fichier ``out``

Voici les étapes qui constitue un test valide :

1. Le test copie le contenu du fichier ``in`` dans le fichier ``out``.
2. Le test effectuer la "Summurization" à l'aide de la commande. 
Il met donc à jour le fichier `out`.
3. Pour valider le test, il compare le fichier ``out`` avec le fichier ``expected``.

Le fichier ``expected`` sera mise à jour manuellement par le développeur lorsque
le comportement du programme changera le résultat final.

Exemple : Modification des balises d'**insertion** et d'**encadrement** du sommaire.
