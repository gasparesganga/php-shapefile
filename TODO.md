# ToDo
Feel free to suggest me anything about already planned features or request new ones.
I cannot commit to any deadline, but I will try my best to develop this library the same way I've always done.


### Support newer dBase formats
The original ESRI Shapefile specifications only allowed dBASE III files (1998 anyone?) and I wrote the library with those specs in mind.
Some GIS software have started to use newer dBASE versions in order to extend the capabilities of the format, even if it was *technically not compliant* with the specs.
This change requires a major rewriting of the dBASE binary reading code, it is going to happen in the next major release.
**Predicted release: 3.0.0**


### Shapefile writing capabilities
This is the most popular request. I have never really needed it since I consider Shapefile more like an *unidirectional exchange format* from GIS software to RDBMS, meaning once data has entered a modern geodatabase, it's there to stay.
Nonetheless I understand some people have different uses in their minds. It requires a major effort, thus it will not be ready before the next major release.
It comes with all the headaches related to the reduced amount of data and geometrical precision allowed by the format, so I have to figure out some rational and consistent (and creative!) criteria before even starting to implement it. Any advice is welcome.
**Predicted release: 3.0.0**
