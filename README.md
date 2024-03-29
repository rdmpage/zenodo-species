# Storing species descriptions on Zenodo


- descriptions 
- specimens
- images
- sequences

## Key concepts

What things need DOIs?
How will we have links between things?


## Local database

Need to be able to map local data to Darwin Core (basically spreadsheets)
Need local database to store DOIs (concept and version)

## API

- How do we add things?
- How do we update versions?
- Things have dependencies, how do we store that?

## Idea

- Bundle data up in a single Darwin Core Archive
- Read archive, generate files for adding to Zenodo
- Create a list of all local identifiers that need to be mapped to DOIs
- Generate list of relationships that we need to add (graph of connections)
- Create deposits for each local identifier
- Update files for upload with the deposit ids (which correspond to last part of DOI)
- Generate update for local system (e.g., SQL statements to add DOIs)
- Generate metadata for each deposit (including links between deposits)
- Determine what files need to be added to a desposit
- publish all records to Zenodo
- profit

## Config

Create a YAML file that specifies how to process each data type, what relationships we expect to exist, what file types we need to add.


```
digraph G {
    rankdir=LR;
"Pseudeusemia bursadoides" -> "PNGTY639-13" [label=hasPart];
"Pseudeusemia bursadoides" -> "PNGTY1842-15" [label=hasPart];
"Pseudeusemia bursadoides" -> "GWORB1022-07" [label=hasPart];

"GWORB1022-07" -> "GWORB1022-07-seq" [label=isDocumentedBy];
"PNGTY1842-15" -> "PNGTY1842-15-seq" [label=isDocumentedBy];
"PNGTY639-13" -> "PNGTY639-13-seq" [label=isDocumentedBy];

"GWORB1022-07" -> "BC_ZSM_Lep_02244+1180603316.jpg" [label=isDocumentedBy];
"PNGTY1842-15" -> "PNGTY/960680+1446592332.jpg" [label=isDocumentedBy];
"PNGTY639-13" -> "PNGTY/960680d+1406335524.jpg" [label=isDocumentedBy];

}
```








