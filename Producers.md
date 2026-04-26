The producers represent bins of food waste around a city.
The bins have a position represented by coordinates, have a unique id and a max capacity. They also have a lid that may or may not be properly closed. The bins also have a battery.
They generate messages whenever someone finishes using the bin.
The message identifies the bin that is sending, the user that is identified by an hexadecimal code of 8 characters, and the weight deposited.
The bins also warn when they are reaching 50%, 75% and 90% capacity.
The bins also warn if the lid is not closed properly after usage.
The bins also warn if the battery is running low, warning at 50%, 25% and 10%.
