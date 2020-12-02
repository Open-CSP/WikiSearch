Vue.component('agg', {
 template:`<li ><input type="checkbox"  @change="filter" v-model="$root.selected" v-bind:value="{value:agg.key, key:name}"> {{agg.key}} ({{agg.doc_count}})</li>`,
   props:{
     agg:Object,
     name:String
   },
   computed:{
      selected:{

      }
   },
   data(){
     return {
   hoi:'hoi'
     }
   },
   methods:{
     filter:function(e){
       var root = this.$root;
           root.from = 0;
       var params = {
           action: 'query',
           meta: 'smws',
           format: 'json',
           smfilter: JSON.stringify(root.selected),
           smtitle: root.titleID,
           smexerpt: root.exerptID,
           smaggs: Object.keys(root.aggs).join(),
           smclass:root.main,
          smterm:root.term


          },
      api = new mw.Api();

      api.post(  params ).done( function ( data ) {
       console.log( data );
       root.total = data.result.total;
       root.hits = JSON.parse(data.result.hits);
       root.aggs = JSON.parse(data.result.aggs);



})
     }

   }
 });
