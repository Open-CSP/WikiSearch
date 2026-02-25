CREATE TABLE /*_*/search_history (
    search_history_id INT NOT NULL AUTO_INCREMENT,
    search_query VARCHAR(1024) NOT NULL,
    search_timestamp DATETIME NOT NULL,
    PRIMARY KEY (search_history_id)
) /*$wgDBTableOptions*/;