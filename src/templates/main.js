methods:{
  getNs:function(e){
         console.log(e.target.value);
    var params = {
        action: 'ask',
        query: '[Name::~~*'+e.target.value+'*][[Class::Space]]|?namespace|?Name|sort=Name|order=asc',
        format: 'json'

  },
    api = new mw.Api();

    api.post(  params ).done( function ( data ) {
       console.log(data);
    });

  },
  setrange:function(e, pos){
    this[pos] = e.target.value;
  },
  more:function(prop){
var index = this.open.indexOf(prop);
  if (index > -1) {
    this.open.splice(index, 1);
  }else{
    this.open.push(prop);
     }
  },
  search:function(e){
    var root = this;
    root.loading = true;
    this.from = 0;
   this.term = e.target.value;
    var params = {
        action: 'query',
        meta: 'smws',
        format: 'json',
        smfilter: JSON.stringify(root.selected),
        smtitle: root.titleID,
        smexerpt: root.exerpt,
        smaggs: Object.keys(root.aggs).join(),
        smclass:root.main,
        smterm:e.target.value,
          smdates:JSON.stringify(root.dates)


       },
   api = new mw.Api();

   api.post(  params ).done( function ( data ) {
    console.log( data );
    root.total = data.result.total;
    root.hits = JSON.parse(data.result.hits);
    root.aggs = JSON.parse(data.result.aggs);
    root.loading = false;



  });

},
activepage:function(pager){
  if(pager == (this.from / this.size) + 1){
  return 'active';
}
},
nextz:function(e){
  var root = this;
 root.loading = true;




   if(e.target.innerText.trim() == '<'){
     console.log('back');

    this.from = this.from - this.size;
  }else if(e.target.innerText.trim() == '>'){
        this.from = this.from + this.size;
  }else{
     console.log('nr');
  this.from = Math.ceil(this.size * (e.target.innerText - 1));
}


var params = {
    action: 'query',
    meta: 'smws',
    format: 'json',
    smfilter: JSON.stringify(root.selected),
    smtitle: root.titleID,
    smexerpt: root.exerpt,
    smaggs: Object.keys(root.aggs).join(),
    smclass:root.main,
    smfrom:this.from,
    smterm:root.term,
    smdates:JSON.stringify(root.dates)




   },
api = new mw.Api();

api.post(  params ).done( function ( data ) {
console.log( data );
root.total = data.result.total;
root.hits = JSON.parse(data.result.hits);
root.aggs = JSON.parse(data.result.aggs);
root.loading = false;


});




}

},
computed:{
  svgcurve:function(){
    var sorted = this.aggs.Date.buckets.sort(function(a, b) {
                return a.doc_count - b.doc_count;
              });
  var highest = sorted[sorted.length - 1].doc_count;
  var lowest = sorted[0].doc_count;
  var chunksize = 100 / this.aggs.Date.buckets.length;
  var procent = 10 / highest;
  var svgarray = [[0,0]];
  var svgstring = '<path d="M 0,0 ';
  this.aggs.Date.buckets.forEach(function(item, i){
     svgarray.push([ chunksize * i, item.doc_count * procent]);
    svgstring +=  chunksize * i + ',' +  item.doc_count * procent + ' ';
  });
 svgstring += 100 + ',' + 0 +'" />';
  svgarray.push([100 , 0]);


  const smoothing = 0.15;
console.log(svgarray);
const points = svgarray;

const line = (pointA, pointB) => {
  const lengthX = pointB[0] - pointA[0];
  const lengthY = pointB[1] - pointA[1];
  return {
    length: Math.sqrt(Math.pow(lengthX, 2) + Math.pow(lengthY, 2)),
    angle: Math.atan2(lengthY, lengthX)
  }
};


const controlPoint = (current, previous, next, reverse) => {


  const p = previous || current;
  const n = next || current;

  const o = line(p, n);

  const angle = o.angle + (reverse?Math.PI:0);
  const length = o.length * smoothing;

  const x = current[0] + Math.cos(angle) * length;
  const y = current[1] + Math.sin(angle) * length;
  return [x, y];
};


const bezierCommand = (point, i, a) => {

  const cps = controlPoint(a[i - 1], a[i - 2], point);

  const cpe = controlPoint(point, a[i - 1], a[i + 1], true);
  return `C ${cps[0]},${cps[1]} ${cpe[0]},${cpe[1]} ${point[0]},${point[1]}`;
};


const svgPath = (points, command) => {
  const d = points.reduce((acc, point, i, a) => i === 0?`M ${point[0]},${point[1]}`:`${acc} ${command(point, i, a)}`, '');
  return `<path d="${d}" fill="url(#gradient)"  />`;
};


return  svgPath(points, bezierCommand);


  },
  rangeName:function(){
    var chunksize = 100 / this.aggs.Date.buckets.length;
    return this.aggs.Date.buckets[Math.round(this.rangeTo / chunksize)].key;

  },
  mainloading:function(){
    if(this.loading){
      return 'smws--main smws--loading';
    }else{
      return 'smws--main';
    }

  },
  sort:function(){
    this.aggs.Date.buckets = this.aggs.Date.buckets.filter(function(el){

      if(el.doc_count > 0){
        console.log(el);
        return el;
      }
    }).reverse();


  },
  pagers:function(e){
  if(this.total >= this.size){
    if(this.from == 0){
      var pages = [];
    }else{
      var pages = ['<'];
    }
    var i;
    var step = Math.ceil(this.total / this.size);
    for (i = 0; i < step; i++) {
      pages.push(i + 1)
    }
    if(this.from + this.size >=  this.total - this.size){

    }else{
      pages.push('>');
    }

      return pages;
    }
  },


}
