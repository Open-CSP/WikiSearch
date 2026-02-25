CREATE TABLE /*_*/search_history_aggregates (
    search_query VARCHAR(1024) NOT NULL,
    search_occurrences INT NOT NULL,
    PRIMARY KEY (search_query)
) /*$wgDBTableOptions*/;