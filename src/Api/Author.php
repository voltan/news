<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */
namespace Module\News\Api;

use Pi;
use Pi\Application\Api\AbstractApi;

/*
 * Pi::api('author', 'news')->getFormAuthor();
 * Pi::api('author', 'news')->getFormRole();
 * Pi::api('author', 'news')->setFormValues($story);
 * Pi::api('author', 'news')->getStoryList($author);
 * Pi::api('author', 'news')->getStorySingle($story);
 * Pi::api('author', 'news')->setAuthorStory($story, $time_publish, $status, $authors);
 * Pi::api('author', 'news')->canonizeAuthor($author);
 */

class Author extends AbstractApi
{
    public function getFormAuthor()
    {
        // set info
        $where = array('status' => 1);
        $columns = array('id', 'title');
        $order = array('title DESC', 'id DESC');
        $list = array(0 => '');
        // Get attach count
        $select = Pi::model('author', $this->getModule())->select()->columns($columns)->where($where)->order($order);
        $rowset = Pi::model('author', $this->getModule())->selectWith($select);
        foreach ($rowset as $row) {
            $list[$row->id] = $row->title;
        }
        return $list;
    }

    public function getFormRole()
    {
        // set info
        $where = array('status' => 1);
        $columns = array('id', 'title');
        $order = array('title DESC', 'id DESC');
        $list = array();
        // Get attach count
        $select = Pi::model('author_role', $this->getModule())->select()->columns($columns)->where($where)->order($order);
        $rowset = Pi::model('author_role', $this->getModule())->selectWith($select);
        foreach ($rowset as $row) {
            $list[$row->id] = $row->toArray();
            $list[$row->id]['name'] = sprintf('role_%s', $row->id);
        }
        return $list;
    }

    public function setFormValues($story)
    {
    	// set info
        $where = array('story' => $story['id']);
        // Get attach count
        $select = Pi::model('author_story', $this->getModule())->select()->where($where);
        $rowset = Pi::model('author_story', $this->getModule())->selectWith($select);
        foreach ($rowset as $row) {
        	$name = sprintf('role_%s', $row->role);
            $story[$name] = $row->author;
        }
        return $story;
    }

    public function setAuthorStory($story, $time_publish, $status, $authors = array())
    {
    	if (!empty($authors)) {
    		//Remove
        	Pi::model('author_story', $this->getModule())->delete(array('story' => $story));
        	// Add
        	foreach ($authors as $author) {
        		if ($author['author'] > 0) {
        			// Set array
            		$values['story'] = $story;
            		$values['time_publish'] = $time_publish;
            		$values['status'] = $status;
            		$values['author'] = $author['author'];
            		$values['role'] = $author['role'];
           	    	// Save
            		$row = Pi::model('author_story', $this->getModule())->createRow();
            		$row->assign($values);
            		$row->save();
        		}
        	}
    	}
    }

    public function getStoryList($author, $roles)
    {
        $story = array();
        foreach ($roles as $role) {
			// set info
			$id = array();
        	$where = array('status' => 1, 'author' => $author, 'role' => $role['id']);
        	$order = array('time_publish DESC', 'id DESC');
        	// Get author
        	$select = Pi::model('author_story', $this->getModule())->select()->where($where)->order($order);
        	$rowset = Pi::model('author_story', $this->getModule())->selectWith($select);
        	foreach ($rowset as $row) {
            	$id[] = $row->story;
        	}
        	// Check and get
        	if (!empty($id)) {
        		$story[$role['id']] = Pi::api('story', 'news')->getListFromIdLight($id);
        	}
        }
        return $story;
    }

    public function getStorySingle($story)
    {
        // Get roles
        $roles = $this->getFormRole();
        // set info
        $list = array();
        $where = array('status' => 1, 'story' => $story,);
        $order = array('time_publish DESC', 'id DESC');
        // Get author
        $select = Pi::model('author_story', $this->getModule())->select()->where($where)->order($order);
        $rowset = Pi::model('author_story', $this->getModule())->selectWith($select);
        foreach ($rowset as $row) {
            $author = Pi::model('author', $this->getModule())->find($row->author)->toArray();
            $list[$row->id] = array();
            $list[$row->id]['role'] = $roles[$row->role]['title'];
            $list[$row->id]['name'] = $author['title'];
            $list[$row->id]['authorUrl'] = Pi::service('url')->assemble('news', array(
                'module'        => $this->getModule(),
                'controller'    => 'author',
                'slug'          => $author['slug'],
            ));;
        }
        return $list;
    }

    public function canonizeAuthor($author)
    {
        // Check
        if (empty($author)) {
            return '';
        }
        // Get config
        $config = Pi::service('registry')->config->read($this->getModule());
        // boject to array
        $author = $author->toArray();
        // Set description
        $author['description'] = Pi::service('markup')->render($author['description'], 'html', 'html');
        // Set times
        $author['time_create_view'] = _date($author['time_create']);
        $author['time_update_view'] = _date($author['time_update']);
        // Set story url
        $author['authorUrl'] = Pi::service('url')->assemble('news', array(
            'module'        => $this->getModule(),
            'controller'    => 'author',
            'slug'          => $author['slug'],
        ));
        // Set image url
        if ($author['image']) {
            // Set image original url
            $author['originalUrl'] = Pi::url(
                sprintf('upload/%s/original/%s/%s', 
                    $config['image_path'], 
                    $author['path'], 
                    $author['image']
                ));
            // Set image large url
            $author['largeUrl'] = Pi::url(
                sprintf('upload/%s/large/%s/%s', 
                    $config['image_path'], 
                    $author['path'], 
                    $author['image']
                ));
            // Set image medium url
            $author['mediumUrl'] = Pi::url(
                sprintf('upload/%s/medium/%s/%s', 
                    $config['image_path'], 
                    $author['path'], 
                    $author['image']
                ));
            // Set image thumb url
            $author['thumbUrl'] = Pi::url(
                sprintf('upload/%s/thumb/%s/%s', 
                    $config['image_path'], 
                    $author['path'], 
                    $author['image']
                ));
        } else {
            // Set image original url
            $author['originalUrl'] = Pi::url('static/avatar/local/origin.png');
            // Set image large url
            $author['largeUrl'] = Pi::url('static/avatar/local/xxlarge.png');;
            // Set image medium url
            $author['mediumUrl'] = Pi::url('static/avatar/local/xlarge.png');;
            // Set image thumb url
            $author['thumbUrl'] = Pi::url('static/avatar/local/large.png');;
        }
        // return author
        return $author; 
    }
}