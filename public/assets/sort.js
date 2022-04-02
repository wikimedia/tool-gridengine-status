$(document).ready(function() {
  var isNumber = function ( text ) {
    return /^\d+$/.test( text );
  };

  var compare = function ( a, b ) {
    if (a === b) {
      return 0;
    }

    return a < b ? -1 : 1;
  };

  var numberSplit = function ( text ) {
    return text.split( /(\d+)/ )
        .filter( function ( item ) {
          return !!item;
        } );
  };

  Tablesort.extend( 'natural', function ( item ) {
    return true;
  }, function ( a, b ) {
    var aSplit = numberSplit( a ),
        bSplit = numberSplit( b );

    while ( true ) {
      var aElem = aSplit.shift(),
          bElem = bSplit.shift();

      if ( !aElem && !bElem ) {
        return 0;
      } else if ( !aElem ) {
        return -1;
      } else if ( !bElem ) {
        return 1;
      }

      if ( isNumber( aElem ) && isNumber( bElem ) ) {
        aElem = parseInt( aElem, 10 );
        bElem = parseInt( bElem, 10 );
      }

      var result = compare( aElem, bElem );
      if ( result !== 0 ) {
        // no clue why -result instead of result is needed
        return -result;
      }
    }
  } );

  $('table.tablesort').each(function(idx, elm) {
    new Tablesort(elm);
  });
});
