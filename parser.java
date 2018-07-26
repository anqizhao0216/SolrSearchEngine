package hw5;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileNotFoundException;
import java.io.IOException;
import java.io.PrintWriter;

import org.apache.tika.exception.TikaException;
import org.apache.tika.metadata.Metadata;
import org.apache.tika.parser.ParseContext;
import org.apache.tika.parser.html.HtmlParser;
import org.apache.tika.sax.BodyContentHandler;

import org.xml.sax.SAXException;

public class parser {

   public static void main(final String[] args) throws IOException,SAXException, TikaException, FileNotFoundException {
	  File output = new File("big.txt");
	  PrintWriter writer = new PrintWriter(output);
	  File input = new File("/Users/anqizhao/Downloads/solr-7.2.1/server/solr/newsdays");
	  int count = 0;
	  for (File e: input.listFiles()) {
		  if (count == 0) {
			  count++;
			  continue;
		  }
	      BodyContentHandler handler = new BodyContentHandler(-1);
	      Metadata metadata = new Metadata();
	      FileInputStream inputstream = new FileInputStream(e);
	      ParseContext pcontext = new ParseContext();
	      try {
		      HtmlParser htmlparser = new HtmlParser();
		      htmlparser.parse(inputstream, handler, metadata,pcontext);
		      writer.println(handler.toString().replaceAll("\\s+"," "));
//		      writer.println("Metadata of the document:");
		      String[] metadataNames = metadata.names();
		      for(String name : metadataNames) {
		    	 writer.println(name + ":   " + metadata.get(name));
		      }
			  System.out.println(count++);
	      } catch(Exception e1) {
	    	  e1.printStackTrace();
	      }
	  }
   }
}